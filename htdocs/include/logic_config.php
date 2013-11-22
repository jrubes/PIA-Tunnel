<?php
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */

// only show this form if the user has logged in
$_auth->authenticate();

/* load list of available connections into SESSION */
if(array_key_exists('ovpn', $_SESSION) !== true ){
  if( VPN_ovpn_to_session() !== true ){
    echo "<div id=\"feedback\" class=\"feedback\">FATAL ERROR: Unable to get list of VPN connections!</div>\n";
    return false;
  }
}


//act on $CMD variable
switch($_REQUEST['cmd']){
  case 'vpn':
    $disp_body .= disp_vpn_default();
    break;
  case 'vpn_store';
    if( $_token->pval($_POST['token'], 'update VPN username and password') === true ){
      //update user settings
      $disp_body .= update_user_settings();
    }else{
      $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
    }
    //show inout forms again
    $disp_body .= disp_vpn_default();
    break;

  case 'network':
    $disp_body .= disp_network_default();
    break;

  case 'store_setting':
    // $_POST['store_fields'] contains a list of $_POST variables

    //if( $_token->pval($_POST['token'], 'handle user request - update settings.conf - '.rtrim($_POST['store_fields'], ',')) === true ){
    if( $_token->pval($_POST['token'], 'handle user request - update settings.conf') === true ){
      if(array_key_exists('store_fields', $_POST) !== true ){
        //opened by URL
        $disp_body .= disp_network_default();
        break;
      }

      /* check for restart operations first */
      if( array_key_exists('restart_firewall', $_POST ) === true && $_POST['restart_firewall'] != '' ){
        $_services->firewall_fw('stop');
        $_services->firewall_fw('start');
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Firewall has been restarted</div>\n";
        $disp_body .= disp_network_default();
        break;
      }

      if( array_key_exists('restart_dhcpd', $_POST) ){
        $ret = $_services->dhcpd_restart();
        if( $ret === true ){
          $disp_body .= "<div id=\"feedback\" class=\"feedback\">dhcpd has been restarted</div>\n";
        }else{
          $disp_body .= "<div id=\"feedback\" class=\"feedback\">".nl2br($ret[1])."</div>\n";
        }
        $disp_body .= disp_network_default();
        break;
      }

      if( array_key_exists('restart_network', $_POST ) === true && $_POST['restart_network'] != '' ){
        $_services->network_restart();
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">All network interfaces have been restarted</div>\n";
        $disp_body .= disp_network_default();
        break;
      }

      $ret_save = $_settings->save_settings_logic($_POST['store_fields']);
      VPN_generate_interfaces();
      VPN_generate_dhcpd_conf(); //create new dhcpd.conf file
      $_pia->rebuild_autostart();
      $disp_body .= "<div id=\"feedback\" class=\"feedback\">Settings updated</div>\n";
      $disp_body .= disp_network_default();
      break;


    }else{
      $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
      $disp_body .= disp_network_default();
    }
    break;

  default :
    $disp_body .= '<h2>Please select a menu option</h2>';
}


$disp_body .= '<script type="text/javascript">'
				.'var timr1=setInterval(function(){'
					.'var _overview = new OverviewObj();'
					.'_overview.clean_feedback();'
				.'},2500);'
				.'</script>';







/* FUNCTIONS - move into functions file later */

/**
 * function calls a script to generate a new /etc/network/interfaces
 * based on settings.conf
 */
function VPN_generate_interfaces(){
  exec("sudo /pia/include/network-interfaces.sh"); //write new dhcpd.conf
}

/**
 * function to generate a new dhcpd.conf file after a config change
 */
function VPN_generate_dhcpd_conf(){
  //are not  controlled by this
  $template = dhcpd_process_template();
  $save = escapeshellarg($template);
  exec("sudo /pia/include/dhcpd-reconfigure.sh $save"); //write new dhcpd.conf
}

/**
 * function to scan $_POST for any settings.conf array values
 *  Warning: function very loopy - needs to be optimized
 * @param string $match=null *optional* name of "$_POST key string array" to return - get all if null
 * @return array,bool Array containing one storage array name per key or FALSE if none where found
 */
function VPN_get_post_storage_arrays($match=null){
  global $_settings;
  $settings = $_settings->get_settings();
  $ret = array();

  reset($_POST);
  foreach( $_POST as $key => $val ){

    //$_POST keys are md5() of the setting names so loop over $settings to find a match
    reset($settings);
    $found = false;
    foreach( $settings as $set_key => $set_val ){
      if( $set_key === $key ){
        $found = true;
        break;
      }
    }

    if( $found === true ){
      if($_settings->is_settings_array($set_key) === true ){
        $name_only = substr($set_key, 0, strpos($set_key, '[') ); //get only the array name, without key, from $set_key string
        //this is an array, do we know this key already?
        if( array_is_value_unique($ret, $name_only) === true ){
          $ret[] = $name_only;
        }
      }
    }
  }

  if( count($ret) == 0 ){ return false; }
  else{ return $ret; }
}


/**
 * function to modify /pia/include/dhcpd.conf in RAM and return the changes
 * @global object $_files
 * @return string,bool string containing the modified dhcpd.conf file or false on error
 */
function dhcpd_process_template(){
  global $_files;
  global $_settings;
  $templ = $_files->readfile('/pia/include/dhcpd.conf');
  $subnet_templ = "subnet SUBNET_IP_HERE netmask NETWORK_MASK_HERE {\n"
                  ."  range IP_RANGE_HERE;\n"
                  ."  option routers ROUTER_IP_HERE;\n"
                  ."  option broadcast-address BROADCAST_HERE;\n"
                  ."}\n";
  $static_templ = "host statichost {\n"
                  ."  hardware ethernet STATIC_MAC_HERE;\n"
                  ."  fixed-address STATIC_IP_HERE;\n"
                  ."}\n";
  $subnet = ''; //contains assembled subnet declarations
  $static_host = ''; //contains assembled static host info
  $settings = $_settings->get_settings();

  //there are two dhcpd subnet config ranges
  for( $x = 1 ; $x < 3 ; ++$x ){
    if( $settings['DHCPD_ENABLED'.$x] == 'yes' ){
      $subnet .= "$subnet_templ\n";
      $SometimesIreallyHatePHP = 1; //passing this int bý reference will save tremendous ammounts of RAM - AWESOME SHIT!
      $subnet = str_replace('SUBNET_IP_HERE', $settings['DHCPD_SUBNET'.$x], $subnet, $SometimesIreallyHatePHP);
      $subnet = str_replace('NETWORK_MASK_HERE', $settings['DHCPD_MASK'.$x], $subnet, $SometimesIreallyHatePHP);
      $subnet = str_replace('IP_RANGE_HERE', $settings['DHCPD_RANGE'.$x], $subnet, $SometimesIreallyHatePHP);
      $subnet = str_replace('BROADCAST_HERE', $settings['DHCPD_BROADCAST'.$x], $subnet, $SometimesIreallyHatePHP);
      $subnet = str_replace('ROUTER_IP_HERE', $settings['DHCPD_ROUTER'.$x], $subnet, $SometimesIreallyHatePHP);
    }
  }

  //static IP assignment
  if( $settings['DHCPD_STATIC_IP'] != "" && $settings['DHCPD_STATIC_MAC'] != "" ){
    $static_host = $static_templ;
    $static_host = str_replace('STATIC_MAC_HERE', $settings['DHCPD_STATIC_MAC'], $static_host, $SometimesIreallyHatePHP);
    $static_host = str_replace('STATIC_IP_HERE', $settings['DHCPD_STATIC_IP'], $static_host, $SometimesIreallyHatePHP);
  }

  // Global Option - NAMESERVERS is an array which may contain multiple entries, loop over it
  $NAMESERVERS = $_settings->get_settings_array('NAMESERVERS');
  $ins_dns = '';
  foreach( $NAMESERVERS as $DNS){
    $ins_dns .= ($ins_dns === '' ) ? $DNS[1] : ", $DNS[1]";
  }
  $templ = str_replace('DNSSERVER_HERE', $ins_dns, $templ, $SometimesIreallyHatePHP);

  //all done - return
  return $templ.$subnet.$static_host;

}

/**
 * returns the default UI for this option
 * @return string string with HTML for body of this page
 */
function disp_vpn_default(){
  global $_token;
  $user = VPN_get_user();

  $pass = array('update VPN username and password');
  $tokens = $_token->pgen($pass);

  $disp_body = '';
  /* show Username and Password fields - expand this for more VPN providers */
  $disp_body .= '<div><h2>PIA User Settings</h2>';
  $disp_body .= 'You may update your PIA username and password below.';
  $disp_body .= '<form action="/?page=config&amp;cmd=vpn_store&amp;cid=cvpn" method="post">';
  $disp_body .= '<input type="text" name="username" value="'.htmlentities($user['username']).'">';
  $disp_body .= '<input type="password" name="password" value="" placeholder="************">';
  $disp_body .= '<input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= "</form></div>";
  return $disp_body;
}

/**
 * returns the default UI for this option
 * @global object $_settings
 * @return string string with HTML for body of this page
 */
function disp_dhcpd_box_new(){
  global $_settings;
  global $GLOB_disp_network_default_fields;

  $settings = $_settings->get_settings();
  $disp_body = '';

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<h2>DHCP Server  Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  //show two subnets
  for( $x=1 ; $x < 3 ; ++$x )
  {
    $GLOB_disp_network_default_fields .= 'DHCPD_ENABLED'.$x.',';
    $sel = array(
            'id' => 'DHCPD_ENABLED'.$x,
            'selected' => $settings['DHCPD_ENABLED'.$x],
            'onchange' => "toggle(this, 'DHCPD_SUBNET$x,DHCPD_MASK$x,DHCPD_BROADCAST$x,DHCPD_ROUTER$x,DHCPD_RANGE$x', 'no', 'disabled', '', '');",
            array( 'no', 'disabled'),
            array( 'yes', 'enabled')
          );
    $disp_body .= '<tr><td>Subnet '.$x.'</td><td>'.build_select($sel).'</td></tr>'."\n";
    //Subnet 1 settings
    $disabled = ($settings['DHCPD_ENABLED'.$x] === 'no') ? 'disabled' : ''; //disable input fields when DHCP is set
    $GLOB_disp_network_default_fields .= 'DHCPD_SUBNET'.$x.',';
    $disp_body .= '<tr><td>Subnet IP</td><td><input '.$disabled.' type="text" id="DHCPD_SUBNET'.$x.'" name="DHCPD_SUBNET'.$x.'" value="'.htmlspecialchars($settings['DHCPD_SUBNET'.$x]).'"></td></tr>'."\n";
    $GLOB_disp_network_default_fields .= 'DHCPD_MASK'.$x.',';
    $disp_body .= '<tr><td>Subnetmask</td><td><input '.$disabled.' type="text" id="DHCPD_MASK'.$x.'" name="DHCPD_MASK'.$x.'" value="'.htmlspecialchars($settings['DHCPD_MASK'.$x]).'"></td></tr>'."\n";
    $GLOB_disp_network_default_fields .= 'DHCPD_BROADCAST'.$x.',';
    $disp_body .= '<tr><td>Broadcast IP</td><td><input '.$disabled.' type="text" id="DHCPD_BROADCAST'.$x.'" name="DHCPD_BROADCAST'.$x.'" value="'.htmlspecialchars($settings['DHCPD_BROADCAST'.$x]).'"></td></tr>'."\n";
    $GLOB_disp_network_default_fields .= 'DHCPD_ROUTER'.$x.',';
    $disp_body .= '<tr><td>Router/Gateway</td><td><input '.$disabled.' type="text" id="DHCPD_ROUTER'.$x.'" name="DHCPD_ROUTER'.$x.'" value="'.htmlspecialchars($settings['DHCPD_ROUTER'.$x]).'"></td></tr>'."\n";
    $GLOB_disp_network_default_fields .= 'DHCPD_RANGE'.$x.',';
    $disp_body .= '<tr><td>IP Range</td><td><input '.$disabled.' class="long" type="text" id="DHCPD_RANGE'.$x.'" name="DHCPD_RANGE'.$x.'" value="'.htmlspecialchars($settings['DHCPD_RANGE'.$x]).'"></td></tr>'."\n";;
    $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  }

  //basic interface and network
  $disabled = ($settings['DHCPD_ENABLED1'] === 'no' && $settings['DHCPD_ENABLED2'] === 'no' ) ? 'disabled' : '';
  $GLOB_disp_network_default_fields .= 'DHCPD_STATIC_IP,';
  $disp_body .= '<tr><td>Fixed IP</td><td><input '.$disabled.' type="text" name="DHCPD_STATIC_IP" value="'.htmlspecialchars($settings['DHCPD_STATIC_IP']).'" title="Use as IP in \'General Settings\' - \'Forward IP\'"></td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'DHCPD_STATIC_MAC,';
  $disp_body .= '<tr><td>MAC for IP</td><td><input '.$disabled.' type="text" onkeyup="this.value = this.value.replace(/-/g, \':\');" name="DHCPD_STATIC_MAC" value="'.htmlspecialchars($settings['DHCPD_STATIC_MAC']).'" placeholder="00:00:00:00:00:00" title="MAC separated by colons (:)"></td></tr>'."\n";



  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_dhcpd" value="Restart dhcpd">';
  $disp_body .= '</div>';

  return $disp_body;
}

/**
 * returns the default UI for this option
 * @global object $_settings
 * @return string string with HTML for body of this page
 */
function disp_dhcpd_box($tokens){
  global $_settings;
  $settings = $_settings->get_settings();
  $disp_body = '';
  $fields = ''; //comma separate list of settings offered here

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="dhcpd_settings">';
  $disp_body .= '<h2>DHCP Server  Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  //show two subnets
  for( $x=1 ; $x < 3 ; ++$x )
  {
    $fields .= 'DHCPD_ENABLED'.$x.',';
    $sel = array(
            'id' => 'DHCPD_ENABLED'.$x,
            'selected' => $settings['DHCPD_ENABLED'.$x],
            'onchange' => "toggle(this, 'DHCPD_SUBNET$x,DHCPD_MASK$x,DHCPD_BROADCAST$x,DHCPD_ROUTER$x,DHCPD_RANGE$x', 'no', 'disabled', '');",
            array( 'no', 'disabled'),
            array( 'yes', 'enabled')
          );
    $disp_body .= '<tr><td>Subnet '.$x.'</td><td>'.build_select($sel).'</td></tr>'."\n";
    //Subnet 1 settings
    $disabled = ($settings['DHCPD_ENABLED'.$x] === 'no') ? 'disabled' : ''; //disable input fields when DHCP is set
    $fields .= 'DHCPD_SUBNET'.$x.',';
    $disp_body .= '<tr><td>Subnet IP</td><td><input '.$disabled.' type="text" id="DHCPD_SUBNET'.$x.'" name="DHCPD_SUBNET'.$x.'" value="'.htmlspecialchars($settings['DHCPD_SUBNET'.$x]).'"></td></tr>'."\n";
    $fields .= 'DHCPD_MASK'.$x.',';
    $disp_body .= '<tr><td>Subnetmask</td><td><input '.$disabled.' type="text" id="DHCPD_MASK'.$x.'" name="DHCPD_MASK'.$x.'" value="'.htmlspecialchars($settings['DHCPD_MASK'.$x]).'"></td></tr>'."\n";
    $fields .= 'DHCPD_BROADCAST'.$x.',';
    $disp_body .= '<tr><td>Broadcast IP</td><td><input '.$disabled.' type="text" id="DHCPD_BROADCAST'.$x.'" name="DHCPD_BROADCAST'.$x.'" value="'.htmlspecialchars($settings['DHCPD_BROADCAST'.$x]).'"></td></tr>'."\n";
    $fields .= 'DHCPD_ROUTER'.$x.',';
    $disp_body .= '<tr><td>Router/Gateway</td><td><input '.$disabled.' type="text" id="DHCPD_ROUTER'.$x.'" name="DHCPD_ROUTER'.$x.'" value="'.htmlspecialchars($settings['DHCPD_ROUTER'.$x]).'"></td></tr>'."\n";
    $fields .= 'DHCPD_RANGE'.$x.',';
    $disp_body .= '<tr><td>IP Range</td><td><input '.$disabled.' class="long" type="text" id="DHCPD_RANGE'.$x.'" name="DHCPD_RANGE'.$x.'" value="'.htmlspecialchars($settings['DHCPD_RANGE'.$x]).'"></td></tr>'."\n";;
    $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  }

  //basic interface and network
  $disabled = ($settings['DHCPD_ENABLED1'] === 'no' && $settings['DHCPD_ENABLED2'] === 'no' ) ? 'disabled' : '';
  $fields .= 'DHCPD_STATIC_IP,';
  $disp_body .= '<tr><td>Fixed IP</td><td><input '.$disabled.' type="text" name="DHCPD_STATIC_IP" value="'.htmlspecialchars($settings['DHCPD_STATIC_IP']).'"></td></tr>'."\n";
  $fields .= 'DHCPD_STATIC_MAC,';
  $disp_body .= '<tr><td>MAC for IP</td><td><input '.$disabled.' type="text" onkeyup="this.value = this.value.replace(/-/g, \':\');" name="DHCPD_STATIC_MAC" value="'.htmlspecialchars($settings['DHCPD_STATIC_MAC']).'" placeholder="00:00:00:00:00:00" title="MAC separated by colons (:)"></td></tr>'."\n";



  $disp_body .= "</table>\n";
  $disp_body .= '<input type="hidden" name="store_fields" value="'.  rtrim($fields, ',').'">';
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_dhcpd" value="Restart dhcpd">';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= '</form>';
  $disp_body .= '</div>';

  return $disp_body;
}

function disp_pia_daemon_box_new(){
  global $_settings;
  global $GLOB_disp_network_default_fields;

  $settings = $_settings->get_settings();
  $disp_body = '';

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<h2>PIA Daemon Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  $GLOB_disp_network_default_fields .= 'DAEMON_ENABLED,';
  $sel = array(
            'id' => 'DAEMON_ENABLED',
            'selected' =>  $settings['DAEMON_ENABLED'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Start after OS boot </td><td>'.build_select($sel).'</td></tr>'."\n";

  //Failover connection selection - fix hard coded loop later
  $fovers = 0;
  $GLOB_disp_network_default_fields .= 'MYVPN,';
  for( $x = 0 ; $x < 30 ; ++$x ){
    if( array_key_exists('MYVPN['.$x.']', $settings) === true ){

      $ovpn = VPN_get_connections('MYVPN['.$x.']', array( 'selected' => $settings['MYVPN['.$x.']'], array( '', '')) ); //empty array creates a space between the default selection
      $disp_body .= '<tr><td>Failover '.$x.'</td><td>'.$ovpn.'</td></tr>'."\n";
      ++$fovers;
    }
  }


  $ovpn = VPN_get_connections('MYVPN['.$fovers.']', array('initial' => 'empty'));
  $disp_body .= '<tr><td>Add Failover</td><td>'.$ovpn.'</td></tr>'."\n";


  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= '</div>';

  return $disp_body;
}

function disp_pia_daemon_box($tokens){
  global $_settings;
  $settings = $_settings->get_settings();
  $disp_body = '';
  $fields = ''; //comma separate list of settings offered here

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="daemon_settings">';
  $disp_body .= '<h2>PIA Daemon Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  $fields .= 'DAEMON_ENABLED,';
  $sel = array(
            'id' => 'DAEMON_ENABLED',
            'selected' =>  $settings['DAEMON_ENABLED'],
            'onchange' => "toggle(this, 'MYVPN[0],MYVPN[1],MYVPN[2],MYVPN[3],MYVPN[4],MYVPN[5],MYVPN[6],MYVPN[7],MYVPN[8],MYVPN[9],MYVPN[10]', 'no', 'disabled', '');",
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Start after OS boot </td><td>'.build_select($sel).'</td></tr>'."\n";

  //Failover connection selection - fix hard coded loop later
  $fovers = 0;
  $fields .= 'MYVPN,';
  for( $x = 0 ; $x < 30 ; ++$x ){
    if( array_key_exists('MYVPN['.$x.']', $settings) === true ){
      if( $settings['DAEMON_ENABLED'] === 'no' ){
        $ovpn = VPN_get_connections('MYVPN['.$x.']', array( 'selected' => $settings['MYVPN['.$x.']'], 'disabled' => '', array( '', '')) ); //empty array creates a space between the default selection
      }else{
        $ovpn = VPN_get_connections('MYVPN['.$x.']', array( 'selected' => $settings['MYVPN['.$x.']'], array( '', '')) ); //empty array creates a space between the default selection
      }

      $disp_body .= '<tr><td>Failover '.$x.'</td><td>'.$ovpn.'</td></tr>'."\n";
      ++$fovers;
    }
  }


  if( $settings['DAEMON_ENABLED'] === 'no' ){
    $ovpn = VPN_get_connections('MYVPN['.$fovers.']', array('initial' => 'empty', 'disabled' => '') );
  }else{
    $ovpn = VPN_get_connections('MYVPN['.$fovers.']', array('initial' => 'empty'));
  }
  $disp_body .= '<tr><td>Add Failover</td><td>'.$ovpn.'</td></tr>'."\n";


  $disp_body .= "</table>\n";
  $disp_body .= '<input type="hidden" name="store_fields" value="'.  rtrim($fields, ',').'">';
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= '</form>';
  $disp_body .= '</div>';

  return $disp_body;
}

function disp_webui_box($tokens){
  global $_settings;


  $settings = $_settings->get_settings();
  $disp_body = '';
  $fields = ''; //comma separate list of settings offered here

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="web_ui_settings">';
  $disp_body .= '<h2>Web-UI Settings</h2>'."\n";
  $disp_body .= "<table>\n";
  $disp_body .= '<tr><td>Web-UI Username</td><td><input type="text" name="WEB_UI_USER" value="'.htmlspecialchars($settings['WEB_UI_USER']).'"></td></tr>'."\n";
  $disp_body .= '<tr><td>Web-UI Password</td><td><input type="password" name="WEB_UI_PASSWORD" value="" placeholder="*********"></td></tr>'."\n";

  $fields .= 'WEB_UI_COOKIE_LIFETIME,';
  $disp_body .= '<tr><td>Remember Me for</td><td><input type="text" class="short" name="WEB_UI_COOKIE_LIFETIME" value="'.htmlspecialchars($settings['WEB_UI_COOKIE_LIFETIME']).'"> days</td></tr>'."\n";

  $disp_body .= "</table>\n";
  $disp_body .= '<input type="hidden" name="store_fields" value="'.  rtrim($fields, ',').'">';
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= '</form>';
  $disp_body .= '</div>';

  return $disp_body;

}

function disp_general_box(){
  global $_settings;
  global $GLOB_disp_network_default_fields;

  $settings = $_settings->get_settings();
  $disp_body = '';

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<h2>General Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  //basic interface and network
  $GLOB_disp_network_default_fields .= 'FORWARD_PORT_ENABLED,';
  $sel = array(
          'id' => 'FORWARD_PORT_ENABLED',
          'selected' =>  $settings['FORWARD_PORT_ENABLED'],
          'onchange' => "toggle(this, 'FORWARD_IP', 'no', 'disabled', '', '');",
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>Enable Port Forwarding</td><td>'.build_select($sel).'</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'FORWARD_IP,';
  $disabled = ( $settings['FORWARD_PORT_ENABLED'] === 'no' ) ? ' disabled ' : '';
  $disp_body .= '<tr><td>Forward IP</td><td><input type="text" '.$disabled.' id="FORWARD_IP" name="FORWARD_IP" value="'.htmlspecialchars($settings['FORWARD_IP']).'" title="Use as IP in \'DHCP Server Settings\' - \'Fixed IP\'"></td></tr>'."\n";

  //VM LAN segment forwarding
  $GLOB_disp_network_default_fields .= 'FORWARD_VM_LAN,';
  $sel = array(
            'id' => 'FORWARD_VM_LAN',
            'selected' =>  $settings['FORWARD_VM_LAN'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>VPN Gateway for VM LAN</td><td>'.build_select($sel).'</td></tr>'."\n";
  //use public LAN segment for forwarding
  $GLOB_disp_network_default_fields .= 'FORWARD_PUBLIC_LAN,';
  $sel = array(
            'id' => 'FORWARD_PUBLIC_LAN',
            'selected' =>  $settings['FORWARD_PUBLIC_LAN'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>VPN Gateway for public LAN</td><td>'.build_select($sel).'</td></tr>'."\n";


  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

  //now FIREWALL_IF_WEB options
  $use = 'FIREWALL_IF_WEB';
  $GLOB_disp_network_default_fields .= 'FIREWALL_IF_WEB,';
  $fw_ssh = $_settings->get_settings_array($use);
  //Wvar_dump($fw_ssh);die();
  $sel = array(
            'id' => $use,
            'selected' =>  $fw_ssh,
            array( 'FIREWALL_IF_WEB[0]', 'eth0'),
            array( 'FIREWALL_IF_WEB[1]', 'eth1')
          );
  $disp_body .= '<tr><td>Allow web-UI access on</td><td>'.build_checkbox($sel).'</td></tr>'."\n";


  $use = 'FIREWALL_IF_SSH';
  $GLOB_disp_network_default_fields .= 'FIREWALL_IF_SSH,';
  $fw_ssh = $_settings->get_settings_array($use);
  //Wvar_dump($fw_ssh);die();
  $sel = array(
            'id' => $use,
            'selected' =>  $fw_ssh,
            array( 'FIREWALL_IF_SSH[0]', 'eth0'),
            array( 'FIREWALL_IF_SSH[1]', 'eth1')
          );
  $disp_body .= '<tr><td>Allow ssh connections on</td><td>'.build_checkbox($sel).'</td></tr>'."\n";


  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disp_body .= '<tr><td>Web-UI Username</td><td><input type="text" name="WEB_UI_USER" value="'.htmlspecialchars($settings['WEB_UI_USER']).'"></td></tr>'."\n";
  $disp_body .= '<tr><td>Web-UI Password</td><td><input type="password" name="WEB_UI_PASSWORD" value="" placeholder="*********"></td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'WEB_UI_COOKIE_LIFETIME,';
  $disp_body .= '<tr><td>Remember Me for</td><td><input type="text" class="short" name="WEB_UI_COOKIE_LIFETIME" value="'.htmlspecialchars($settings['WEB_UI_COOKIE_LIFETIME']).'"> days</td></tr>'."\n";


  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_firewall" value="Restart Firewall">';
  $disp_body .= '</div>';

  return $disp_body;
}

function disp_advanced_box(){
  global $_settings;
  global $GLOB_disp_network_default_fields;

  $settings = $_settings->get_settings();
  $disp_body = '';

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<h2>Advanced Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  //interface assignment
  $GLOB_disp_network_default_fields .= 'IF_EXT,';
  $sel = array(
          'id' => 'IF_EXT',
          'selected' =>  $settings['IF_EXT'],
          array( 'eth0', 'eth0'),
          array( 'eth1', 'eth1'),
          array( 'tun0', 'tun0')
        );
  $disp_body .= '<tr><td>Public LAN interface</td><td>'.build_select($sel).'</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'IF_INT,';
  $sel = array(
          'id' => 'IF_INT',
          'selected' =>  $settings['IF_INT'],
          array( 'eth0', 'eth0'),
          array( 'eth1', 'eth1'),
          array( 'tun0', 'tun0')
        );
  $disp_body .= '<tr><td>VM LAN interface</td><td>'.build_select($sel).'</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'IF_TUNNEL,';
  $sel = array(
          'id' => 'IF_TUNNEL',
          'selected' =>  $settings['IF_TUNNEL'],
          array( 'eth0', 'eth0'),
          array( 'eth1', 'eth1'),
          array( 'tun0', 'tun0')
        );
  $disp_body .= '<tr><td>VPN interface</td><td>'.build_select($sel).'</td></tr>'."\n";

  //eth0
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disabled = ($settings['IF_ETH0_DHCP'] === 'yes') ? 'disabled' : ''; //disable input fields when DHCP is set
  $GLOB_disp_network_default_fields .= 'IF_ETH0_DHCP,';
  $sel = array(
          'id' => 'IF_ETH0_DHCP',
          'selected' => $settings['IF_ETH0_DHCP'],
          'onchange' => "toggle(this, 'IF_ETH0_IP,IF_ETH0_SUB,IF_ETH0_GW', 'yes', 'disabled', '', '');",
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>eth0 use DHCP</td><td>'.build_select($sel).'</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'IF_ETH0_IP,';
  $disp_body .= '<tr><td>eth0 IP</td><td><input '.$disabled.' type="text" id="IF_ETH0_IP" name="IF_ETH0_IP" value="'.$settings['IF_ETH0_IP'].'"></td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'IF_ETH0_SUB,';
  $disp_body .= '<tr><td>eth0 Subnet</td><td><input '.$disabled.' type="text" id="IF_ETH0_SUB" name="IF_ETH0_SUB" value="'.$settings['IF_ETH0_SUB'].'"></td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'IF_ETH0_GW,';
  $disp_body .= '<tr><td>eth0 Gateway</td><td><input '.$disabled.' type="text" id="IF_ETH0_GW" name="IF_ETH0_GW" value="'.$settings['IF_ETH0_GW'].'"></td></tr>'."\n";

  //eth1
  $disabled = ($settings['IF_ETH1_DHCP'] === 'yes') ? 'disabled' : ''; //disable input fields when DHCP is set
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'IF_ETH1_DHCP,';
  $sel = array(
          'id' => 'IF_ETH1_DHCP',
          'selected' => $settings['IF_ETH1_DHCP'],
          'onchange' => "toggle(this, 'IF_ETH1_IP,IF_ETH1_SUB,IF_ETH1_GW', 'yes', 'disabled', '', '');",
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>eth1 use DHCP</td><td>'.build_select($sel).'</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'IF_ETH1_IP,';
  $disp_body .= '<tr><td>eth1 IP</td><td><input '.$disabled.' type="text" id="IF_ETH1_IP" name="IF_ETH1_IP" value="'.$settings['IF_ETH1_IP'].'"></td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'IF_ETH1_SUB,';
  $disp_body .= '<tr><td>eth1 Subnet</td><td><input '.$disabled.' type="text" id="IF_ETH1_SUB" name="IF_ETH1_SUB" value="'.$settings['IF_ETH1_SUB'].'"></td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'IF_ETH1_GW,';
  $disp_body .= '<tr><td>eth1 Gateway</td><td><input '.$disabled.' type="text" id="IF_ETH1_GW" name="IF_ETH1_GW" value="'.$settings['IF_ETH1_GW'].'"></td></tr>'."\n";

  //DNS
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'NAMESERVERS,';
  $disp_body .= '<tr><td>DNS 1</td><td><input type="text" name="NAMESERVERS[0]" value="'.$settings['NAMESERVERS[0]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 2</td><td><input type="text" name="NAMESERVERS[1]" value="'.$settings['NAMESERVERS[1]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 3</td><td><input type="text" name="NAMESERVERS[2]" value="'.$settings['NAMESERVERS[2]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 4</td><td><input type="text" name="NAMESERVERS[3]" value="'.$settings['NAMESERVERS[3]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

  //ping error threshold
  $GLOB_disp_network_default_fields .= 'PING_MAX_LOSS,';
  $sel = array(
            'id' => 'PING_MAX_LOSS',
            'selected' =>  $settings['PING_MAX_LOSS'],
            array( '0', '0%'),
            array( '5', '5%'),
            array( '10', '10%'),
            array( '15', '15%'),
            array( '20', '20%'),
            array( '30', '30%'),
            array( '40', '40%'),
            array( '50', '50%'),
            array( '60', '60%'),
            array( '70', '70%'),
            array( '80', '80%'),
            array( '90', '90%'),
            array( '100', '100%')
          );
  $disp_body .= '<tr><td>Max allowed packet loss</td><td>'.build_select($sel).'</td></tr>'."\n";

  //command line stuff
  $GLOB_disp_network_default_fields .= 'VERBOSE,';
  $sel = array(
            'id' => 'VERBOSE',
            'selected' =>  $settings['VERBOSE'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Verbose</td><td>'.build_select($sel).'</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'VERBOSE_DEBUG,';
  $sel = array(
            'id' => 'VERBOSE_DEBUG',
            'selected' =>  $settings['VERBOSE_DEBUG'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Debug Verbose</td><td>'.build_select($sel).'</td></tr>'."\n";

  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings"> ';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_network" value="Full Network Restart">';
  $disp_body .= '</div>';

  return $disp_body;
}


function disp_network_box($tokens){
  global $_settings;
  $settings = $_settings->get_settings();
  $disp_body = '';
  $fields = ''; //comma separate list of settings offered here

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="network_settings">';
  $disp_body .= '<h2>PIA Network Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  //basic interface and network
  $fields .= 'FORWARD_PORT_ENABLED,';
  $sel = array(
          'id' => 'FORWARD_PORT_ENABLED',
          'selected' =>  $settings['FORWARD_PORT_ENABLED'],
          'onchange' => "toggle(this, 'FORWARD_IP', 'no', 'disabled', '', '');",
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>Enable Port Forwarding</td><td>'.build_select($sel).'</td></tr>'."\n";
  $fields .= 'FORWARD_IP,';
  $disabled = ( $settings['FORWARD_PORT_ENABLED'] === 'no' ) ? ' disabled ' : '';
  $disp_body .= '<tr><td>Forward IP</td><td><input type="text" '.$disabled.' id="FORWARD_IP" name="FORWARD_IP" value="'.htmlspecialchars($settings['FORWARD_IP']).'"></td></tr>'."\n";

  //VM LAN segment forwarding
  $fields .= 'FORWARD_VM_LAN,';
  $sel = array(
            'id' => 'FORWARD_VM_LAN',
            'selected' =>  $settings['FORWARD_VM_LAN'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>VPN Gateway for VM LAN</td><td>'.build_select($sel).'</td></tr>'."\n";
  //use public LAN segment for forwarding
  $fields .= 'FORWARD_PUBLIC_LAN,';
  $sel = array(
            'id' => 'FORWARD_PUBLIC_LAN',
            'selected' =>  $settings['FORWARD_PUBLIC_LAN'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>VPN Gateway for public LAN</td><td>'.build_select($sel).'</td></tr>'."\n";


  //ping error threshold
  $fields .= 'PING_MAX_LOSS,';
  $sel = array(
            'id' => 'PING_MAX_LOSS',
            'selected' =>  $settings['PING_MAX_LOSS'],
            array( '0', '0%'),
            array( '5', '5%'),
            array( '10', '10%'),
            array( '15', '15%'),
            array( '20', '20%'),
            array( '30', '30%'),
            array( '40', '40%'),
            array( '50', '50%'),
            array( '60', '60%'),
            array( '70', '70%'),
            array( '80', '80%'),
            array( '90', '90%'),
            array( '100', '100%')
          );
  $disp_body .= '<tr><td>Max allowed packet loss</td><td>'.build_select($sel).'</td></tr>'."\n";

  //management stuff
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

  //these are array settings so get them first then loop over to display them
  $use = 'FIREWALL_IF_SSH';
  $fields .= 'FIREWALL_IF_SSH,';
  $fw_ssh = $_settings->get_settings_array($use);
  //Wvar_dump($fw_ssh);die();
  $sel = array(
            'id' => $use,
            'selected' =>  $fw_ssh,
            array( 'FIREWALL_IF_SSH[0]', 'eth0'),
            array( 'FIREWALL_IF_SSH[1]', 'eth1')
          );
  //$sel = array_merge($sel, $fw_ssh);
  $disp_body .= '<tr><td>Allow ssh logins on</td><td>'.build_checkbox($sel).'</td></tr>'."\n";


  //now FIREWALL_IF_WEB options
  $use = 'FIREWALL_IF_WEB';
  $fields .= 'FIREWALL_IF_WEB,';
  $fw_ssh = $_settings->get_settings_array($use);
  //Wvar_dump($fw_ssh);die();
  $sel = array(
            'id' => $use,
            'selected' =>  $fw_ssh,
            array( 'FIREWALL_IF_WEB[0]', 'eth0'),
            array( 'FIREWALL_IF_WEB[1]', 'eth1')
          );
  //$sel = array_merge($sel, $fw_ssh);
  $disp_body .= '<tr><td>Allow web logins on</td><td>'.build_checkbox($sel).'</td></tr>'."\n";

  $disp_body .= "</table>\n";
  $disp_body .= '<input type="hidden" name="store_fields" value="'.  rtrim($fields, ',').'">';
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_firewall" value="Restart Firewall">';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= '</form>';
  $disp_body .= '</div>';

  return $disp_body;
}

function disp_system_box($tokens){
  global $_settings;
  $settings = $_settings->get_settings();
  $disp_body = '';
  $fields = ''; //comma separate list of settings offered here

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="system_settings">';
  $disp_body .= '<h2>VM System Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  //interface assignment
  $fields .= 'IF_EXT,';
  $sel = array(
          'id' => 'IF_EXT',
          'selected' =>  $settings['IF_EXT'],
          array( 'eth0', 'eth0'),
          array( 'eth1', 'eth1'),
          array( 'tun0', 'tun0')
        );
  $disp_body .= '<tr><td>Public LAN interface</td><td>'.build_select($sel).'</td></tr>'."\n";
  $fields .= 'IF_INT,';
  $sel = array(
          'id' => 'IF_INT',
          'selected' =>  $settings['IF_INT'],
          array( 'eth0', 'eth0'),
          array( 'eth1', 'eth1'),
          array( 'tun0', 'tun0')
        );
  $disp_body .= '<tr><td>VM LAN interface</td><td>'.build_select($sel).'</td></tr>'."\n";
  $fields .= 'IF_TUNNEL,';
  $sel = array(
          'id' => 'IF_TUNNEL',
          'selected' =>  $settings['IF_TUNNEL'],
          array( 'eth0', 'eth0'),
          array( 'eth1', 'eth1'),
          array( 'tun0', 'tun0')
        );
  $disp_body .= '<tr><td>VPN interface</td><td>'.build_select($sel).'</td></tr>'."\n";

  //eth0
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disabled = ($settings['IF_ETH0_DHCP'] === 'yes') ? 'disabled' : ''; //disable input fields when DHCP is set
  $fields .= 'IF_ETH0_DHCP,';
  $sel = array(
          'id' => 'IF_ETH0_DHCP',
          'selected' => $settings['IF_ETH0_DHCP'],
          'onchange' => "toggle(this, 'IF_ETH0_IP,IF_ETH0_SUB,IF_ETH0_GW', 'yes', 'disabled', '', '');",
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>eth0 use DHCP</td><td>'.build_select($sel).'</td></tr>'."\n";
  $fields .= 'IF_ETH0_IP,';
  $disp_body .= '<tr><td>eth1 IP</td><td><input '.$disabled.' type="text" id="IF_ETH0_IP" name="IF_ETH0_IP" value="'.$settings['IF_ETH0_IP'].'"></td></tr>'."\n";
  $fields .= 'IF_ETH0_SUB,';
  $disp_body .= '<tr><td>eth1 Subnet</td><td><input '.$disabled.' type="text" id="IF_ETH0_SUB" name="IF_ETH0_SUB" value="'.$settings['IF_ETH0_SUB'].'"></td></tr>'."\n";
  $fields .= 'IF_ETH0_GW,';
  $disp_body .= '<tr><td>eth1 Gateway</td><td><input '.$disabled.' type="text" id="IF_ETH0_GW" name="IF_ETH0_GW" value="'.$settings['IF_ETH0_GW'].'"></td></tr>'."\n";

  //eth1
  $disabled = ($settings['IF_ETH1_DHCP'] === 'yes') ? 'disabled' : ''; //disable input fields when DHCP is set
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $fields .= 'IF_ETH1_DHCP,';
  $sel = array(
          'id' => 'IF_ETH1_DHCP',
          'selected' => $settings['IF_ETH1_DHCP'],
          'onchange' => "toggle(this, 'IF_ETH1_IP,IF_ETH1_SUB,IF_ETH1_GW', 'yes', 'disabled', '', '');",
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>eth1 use DHCP</td><td>'.build_select($sel).'</td></tr>'."\n";
  $fields .= 'IF_ETH1_IP,';
  $disp_body .= '<tr><td>eth1 IP</td><td><input '.$disabled.' type="text" id="IF_ETH1_IP" name="IF_ETH1_IP" value="'.$settings['IF_ETH1_IP'].'"></td></tr>'."\n";
  $fields .= 'IF_ETH1_SUB,';
  $disp_body .= '<tr><td>eth1 Subnet</td><td><input '.$disabled.' type="text" id="IF_ETH1_SUB" name="IF_ETH1_SUB" value="'.$settings['IF_ETH1_SUB'].'"></td></tr>'."\n";
  $fields .= 'IF_ETH1_GW,';
  $disp_body .= '<tr><td>eth1 Gateway</td><td><input '.$disabled.' type="text" id="IF_ETH1_GW" name="IF_ETH1_GW" value="'.$settings['IF_ETH1_GW'].'"></td></tr>'."\n";

  //DNS
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $fields .= 'NAMESERVERS,';
  $disp_body .= '<tr><td>DNS 1</td><td><input type="text" name="NAMESERVERS[0]" value="'.$settings['NAMESERVERS[0]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 2</td><td><input type="text" name="NAMESERVERS[1]" value="'.$settings['NAMESERVERS[1]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 3</td><td><input type="text" name="NAMESERVERS[2]" value="'.$settings['NAMESERVERS[2]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 4</td><td><input type="text" name="NAMESERVERS[3]" value="'.$settings['NAMESERVERS[3]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

  //command line stuff
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $fields .= 'VERBOSE,';
  $sel = array(
            'id' => 'VERBOSE',
            'selected' =>  $settings['VERBOSE'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Verbose</td><td>'.build_select($sel).'</td></tr>'."\n";
  $fields .= 'VERBOSE_DEBUG,';
  $sel = array(
            'id' => 'VERBOSE_DEBUG',
            'selected' =>  $settings['VERBOSE_DEBUG'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Debug Verbose</td><td>'.build_select($sel).'</td></tr>'."\n";

  $disp_body .= "</table>\n";
  $disp_body .= '<input type="hidden" name="store_fields" value="'.  rtrim($fields, ',').'">';
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings"> ';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_network" value="Full Network Restart">';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= '</form>';
  $disp_body .= '</div>';

  return $disp_body;
}

/**
 * returns the default UI for this option
 * @global object $_settings
 * @return string string with HTML for body of this page
 */
function disp_network_default(){
  global $_settings;
  global $_token;
  global $GLOB_disp_network_default_fields;
  $GLOB_disp_network_default_fields = '';
  $settings = $_settings->get_settings();
  $disp_body = '';



  $disp_body .= '<noscript><p>please enable javascript to activate the advanced UI</p></noscript>';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnet" method="post">'."\n";

  $disp_body .= disp_general_box();
  $disp_body .= disp_pia_daemon_box_new();
  //$disp_body .= disp_network_box($tokens);
  //$disp_body .= disp_pia_daemon_box($tokens);
  //$disp_body .= disp_webui_box($tokens);
  $disp_body .= '<div class="float_hr"></div>';
  $disp_body .= '<p><a id="toggle_advanced_settings_toggle" class="button" href="" onclick="toggle_hide(\'toggle_advanced_settings\', \'toggle_advanced_settings_toggle\', \'Show Advanced Settings,Hide Advanced Settings\'); return false;">Show Advanced Settings</a></p>';
  $disp_body .= '<div class="float_hr"></div>';
  $disp_body .= '<div id="toggle_advanced_settings">';
  $disp_body .= disp_advanced_box();
  //$disp_body .= disp_system_box($tokens);
  $disp_body .= disp_dhcpd_box_new();
  $disp_body .= '</div>';


  /* protect the form and input elements with a token */
  //$pass = array('handle user request - update settings.conf - '.rtrim($GLOB_disp_network_default_fields, ','));
  $pass = array('handle user request - update settings.conf');
  $tokens = $_token->pgen($pass);

  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= '<input type="hidden" id="store_fields" name="store_fields" value="'.  rtrim($GLOB_disp_network_default_fields, ',').'">';
  $disp_body .= '</form>';
  $disp_body .= '<script type="text/javascript">toggle_hide(\'toggle_advanced_settings\', \'\', \'\');</script>';

  return $disp_body;
}

/**
 * method read /pia/login.conf into an array
 * @return array,bool array with ['name'], ['password'] OR FALSE on failure
 */
function VPN_get_user(){
  //get username and password from file or SESSION
  if( array_key_exists('login.conf', $_SESSION) !== true ){
    $ret = load_login();
    if( $ret !== false ){
      return $ret;
    }
  }
  return $_SESSION['login.conf'];
}
?>