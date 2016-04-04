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
    //$disp_body .= disp_vpn_default();
    $disp_body .= disp_vpn_login();
    break;
  case 'vpn_store';
    if( $_token->pval($_POST['token'], 'update VPN username and password') === true ){
      //update user settings
      $disp_body .= update_user_settings();
    }else{
      $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
    }
    //show inout forms again
    //$disp_body .= disp_vpn_default();
    $disp_body .= disp_vpn_login();
    break;

  case 'network':
    $disp_body .= disp_network_default();
    break;

  case 'store_setting':
    // $_POST['store_fields'] contains a list of $_POST variables

    if( $_token->pval($_POST['token'], 'handle user request - update settings.conf - '.rtrim($_POST['store_fields'], ',')) === true ){
    //if( $_token->pval($_POST['token'], 'handle user request - update settings.conf') === true ){
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
        $_services->firewall_fw('stop');
        $_services->firewall_fw('start');
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Firewall has been restarted</div>\n";
        $disp_body .= disp_network_default();
        break;
      }

      if( array_key_exists('restart_network', $_POST ) === true && $_POST['restart_network'] != '' ){
        $_services->network_restart();
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">All network interfaces have been restarted</div>\n";
        $disp_body .= disp_network_default();
        break;
      }

      if( array_key_exists('GIT_BRANCH', $_POST ) === true && $_POST['GIT_BRANCH'] != '' ){
        global $settings;
          //check if the git branch needs to be switched
          if( $settings['GIT_BRANCH'] !== $_POST['GIT_BRANCH'] ){
            $sarg = escapeshellcmd($_POST['GIT_BRANCH']); //this is not proper!
            exec('cd /pia ; git reset --hard HEAD; git fetch ; git checkout '.$sarg.' &> /dev/null');
            exec('chmod ug+x /pia/pia-setup ; /pia/pia-setup &> /dev/null');
            exec('/pia/pia-update &> /dev/null');

            $_settings->save_settings('GIT_BRANCH', $_POST['GIT_BRANCH']);
            $settings = $_settings->get_settings();
            $_pia->clear_update_status(); //clear cache to refresh update check
            $disp_body .= "<div id=\"feedback\" class=\"feedback\">git branch switched to $_POST[GIT_BRANCH]<br>"
                    . "<a href=\"/?page=main\">Please login again to refresh your settings.</a></div>\n";
            $_auth->logout();
            break;
          }



      }

      $ret_save = $_settings->save_settings_logic($_POST['store_fields']);
      VPN_generate_interfaces();
      VPN_generate_dhcpd_conf(); //create new dhcpd.conf file
      VPN_generate_socks_conf(); //create new danted.conf file
      $_services->dhcpd_service_control();
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
				.'},5500);'
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
  $template = dhcpd_process_template();
  $save = escapeshellarg($template);
  exec("sudo /pia/include/dhcpd-reconfigure.sh $save"); //write new dhcpd.conf
}

/**
 * function to generate a new danted.conf file after a config change
 */
function VPN_generate_socks_conf(){
  $template = socks_process_template();
  $save = escapeshellarg($template);
  exec("sudo /pia/include/sockd-dante-reconfigure.sh $save"); //write new dhcpd.conf
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
      }elseif( $set_key === 'MYVPN[0]' ){
        $ret[] = 'MYVPN';
      }
    }
  }

  if( count($ret) == 0 ){ return false; }
  else{ return $ret; }
}

/**
 * function to modify /pia/include/danted.conf in RAM and return the changes
 * @global object $_files
 * @return string,bool string containing the modified dhcpd.conf file or false on error
 */
function socks_process_template(){
  global $_files;
  global $_settings;
  $SometimesIreallyHatePHP = 1;
  $templ = $_files->readfile('/pia/include/sockd-dante.conf');
  $client_templ = "client pass {\n"
                ."  from: INTERNAL_NETWORK_HERE\n"
                ."  #log: error #connect disconnect\n"
                ."}\n";

  $clients = ''; //contains assembled network declaration
  $internal = ''; //will replace the internal: line
  $settings = $_settings->get_settings();

  //update the $template first
  $templ = str_replace('EXTERNAL_IF_HERE', $settings['IF_TUNNEL'], $templ, $SometimesIreallyHatePHP);

  if( $settings['SOCKS_EXT_ENABLED'] == 'yes' ){
    $internal .= "internal: {$settings['IF_EXT']} port = {$settings['SOCKS_EXT_PORT']}\n";

    // this is a placeholder since it is getting late and I want to test the new function
    $network_info = $settings['SOCKS_EXT_FROM'].' port '.$settings['SOCKS_EXT_FROM_PORTRANGE'].' to: '.$settings['SOCKS_EXT_TO'];

    $tmp = $client_templ;
    $tmp = str_replace('INTERNAL_NETWORK_HERE', $network_info, $tmp, $SometimesIreallyHatePHP);

    $clients .= $tmp;
    unset($tmp);
  }


  if( $settings['SOCKS_INT_ENABLED'] == 'yes' ){
    $internal .= "internal: {$settings['IF_INT']} port = {$settings['SOCKS_INT_PORT']}\n";

    //avoid duplicate entries
    if( $settings['SOCKS_EXT_ENABLED'] == 'no'
            || $settings['SOCKS_EXT_FROM'] !== $settings['SOCKS_INT_FROM']
            || $settings['SOCKS_EXT_FROM_PORTRANGE'] !== $settings['SOCKS_EXT_FROM_PORTRANGE']
            || $settings['SOCKS_EXT_TO'] !== $settings['SOCKS_EXT_TO'] )
    {
      // this is a placeholder since it is getting late and I want to test the new function
      $network_info = $settings['SOCKS_INT_FROM'].' port '.$settings['SOCKS_INT_FROM_PORTRANGE'].' to: '.$settings['SOCKS_INT_TO'];

      $tmp = $client_templ;
      $tmp = str_replace('INTERNAL_NETWORK_HERE', $network_info, $tmp, $SometimesIreallyHatePHP);
    }


    $clients .= $tmp;
    unset($tmp);
  }


  if( $internal == '' || $clients == '' ){ return false; }
  $internal = trim($internal)."\n";
  $templ = str_replace('INTERNAL_SETTING_HERE', $internal, $templ, $SometimesIreallyHatePHP);

  $clients = trim($clients)."\n";
  $templ = str_replace('CLIENT_TEMPLATE_HERE', $clients, $templ, $SometimesIreallyHatePHP);
  //var_dump($templ);
  return $templ;
}



/**
 * function to modify /pia/include/dhcpd.conf in RAM and return the changes
 * @global object $_files
 * @return string,bool string containing the modified dhcpd.conf file or false on error
 */
function dhcpd_process_template(){
  global $_files;
  global $_settings;
  $SometimesIreallyHatePHP = 1;
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
 * generates an HTML overview to enter the VPN username and password.
 * @return string string with HTML for body of this page
 */
function disp_vpn_login(){
  $grouped_providers = group_enabled_providers();
  $forms = '';

  foreach( $grouped_providers as $gname => $group){
    $forms .= generate_provider_group_form( $gname, $group);
  }

  return $forms;
}


/**
 * loops over provider group array to build username and password form
 * @param type $provider_group
 */
function generate_provider_group_form( &$group_name, &$provider_group ){
  global $_token;
  $disp_body = '';
  $plist = '';

  $pass = array('update VPN username and password');
  $tokens = $_token->pgen($pass);


  foreach( $provider_group as $p ){ $plist .= ($plist === '') ? $p : ", $p"; }

  //login config files are shared so $p can be any group member
  $vpn_user = VPN_get_user($p);
  $vpn_provider = htmlentities($p);

  $disp_body .= '<div class="box vpn"><h2>Credentials for '.htmlentities($plist).'</h2>';
  $disp_body .= '<form action="/?page=config&amp;cmd=vpn_store&amp;cid=cvpn" method="post">';
  $disp_body .= '<table><tr>';
  $disp_body .= '<td>Username</td><td><input type="text" name="username" value="'.htmlentities($vpn_user['username']).'"></td>';
  $disp_body .= '</tr><tr>';
  $disp_body .= '<td>Password</td><td><input type="password" name="password" class="long" value="" placeholder="************"></td>';
  $disp_body .= '</tr></table>';
  $disp_body .= '<input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= '<input type="hidden" name="vpn_provider" value="'.$vpn_provider.'">';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= "</form></div>";

  return $disp_body;
}



/**
 * support function for disp_vpn_login()
 * it groups the list of enabled VPN providers by the "auth-user-pass" setting
 * @return array multidimensional array, first key defines group, second key is for group members
 */
function group_enabled_providers(){
  global $_settings;
  $providers = array(); //this is returned

  $enabled = $_settings->get_settings_array('VPN_PROVIDERS');

  foreach( $enabled as $ep ){
    $cmdret = array();
    $ovpns = get_ovpn_list($ep[1]);
    if( !array_key_exists(0, $ovpns) ){ return FALSE; }

    //pick first one of the ovpn files to get "auth-user-pass" setting
    $inj = escapeshellarg('/pia/ovpn/'.$ovpns[0].'.ovpn');
    exec('grep "auth-user-pass" '.$inj.' | gawk -F" " \'{print $2}\' ', $cmdret);
    $providers[$cmdret[0]][] = $ep[1];
  }

  //var_dump($providers);die('end');
  return $providers;
}



/**
 * returns the default UI for this option
 * @return string string with HTML for body of this page
 */
function disp_vpn_default(){
  die('old function - disp_vpn_default()');
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

  $disp_body .= '<div class="box options">';
  $disp_body .= '<h2>DHCP Server</h2>'."\n";
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
    $disp_body .= '<tr><td>Subnet IP '.$x.'</td><td><input '.$disabled.' type="text" id="DHCPD_SUBNET'.$x.'" name="DHCPD_SUBNET'.$x.'" value="'.htmlspecialchars($settings['DHCPD_SUBNET'.$x]).'"></td></tr>'."\n";
    $GLOB_disp_network_default_fields .= 'DHCPD_MASK'.$x.',';
    $disp_body .= '<tr><td>Subnetmask '.$x.'</td><td><input '.$disabled.' type="text" id="DHCPD_MASK'.$x.'" name="DHCPD_MASK'.$x.'" value="'.htmlspecialchars($settings['DHCPD_MASK'.$x]).'"></td></tr>'."\n";
    $GLOB_disp_network_default_fields .= 'DHCPD_BROADCAST'.$x.',';
    $disp_body .= '<tr><td>Broadcast IP '.$x.'</td><td><input '.$disabled.' type="text" id="DHCPD_BROADCAST'.$x.'" name="DHCPD_BROADCAST'.$x.'" value="'.htmlspecialchars($settings['DHCPD_BROADCAST'.$x]).'"></td></tr>'."\n";
    $GLOB_disp_network_default_fields .= 'DHCPD_ROUTER'.$x.',';
    $disp_body .= '<tr><td>Router/Gateway '.$x.'</td><td><input '.$disabled.' type="text" id="DHCPD_ROUTER'.$x.'" name="DHCPD_ROUTER'.$x.'" value="'.htmlspecialchars($settings['DHCPD_ROUTER'.$x]).'"></td></tr>'."\n";
    $GLOB_disp_network_default_fields .= 'DHCPD_RANGE'.$x.',';
    $disp_body .= '<tr><td>IP Range '.$x.'</td><td><input '.$disabled.' class="long" type="text" id="DHCPD_RANGE'.$x.'" name="DHCPD_RANGE'.$x.'" value="'.htmlspecialchars($settings['DHCPD_RANGE'.$x]).'"></td></tr>'."\n";;
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
  $disp_body .= ' &nbsp; <a href="/plugin/phpdhcpd/index.php" target="_blank">Show leases</a>';
  $disp_body .= '</div>';

  return $disp_body;
}

/**
 * returns the default UI for this option
 * @global object $_settings
 * @return string string with HTML for body of this page
 */
function disp_socks_box_new(){
  global $_settings;
  global $GLOB_disp_network_default_fields;

  $settings = $_settings->get_settings();
  $disp_body = '';

  $disp_body .= '<div class="box options">';
  $disp_body .= '<h2>SOCKS 5 Proxy Server</h2>'."\n";
  $disp_body .= '<ul>';
  $disp_body .= '<li>requires at least 256MB RAM or performance will degrade within minutes!</li>';
  $disp_body .= '<li><a href="http://www.KaiserSoft.net/r/?SOCKS5" target="_blank">Support Forum ReadMe/Thread</a></li>';
  $disp_body .= '<li>Currently without authentication so anyone on YOUR network will be able to use the proxy!</i>';
  $disp_body .= "</ul><table>\n";

  $GLOB_disp_network_default_fields .= 'SOCKS_SERVER_TYPE,';
  $sel = array(
          'id' => 'SOCKS_SERVER_TYPE',
          'selected' => $settings['SOCKS_SERVER_TYPE'],
          array( '3proxy', '3proxy'),
          array( 'dante', 'dante')
        );
  $disp_body .= '<tr><td>Server Software</td><td>'.build_select($sel).'</td></tr>'."\n";
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

  $GLOB_disp_network_default_fields .= 'SOCKS_EXT_ENABLED,';
  $sel = array(
          'id' => 'SOCKS_EXT_ENABLED',
          'selected' => $settings['SOCKS_EXT_ENABLED'],
          'onchange' => "toggle(this, 'SOCKS_EXT_PORT,SOCKS_EXT_FROM,SOCKS_EXT_TO,SOCKS_EXT_FROM_PORTRANGE', 'no', 'disabled', '', '');",
          array( 'no', 'disabled'),
          array( 'yes', 'enabled')
        );
  $disp_body .= '<tr><td>Public LAN ('.$settings['IF_EXT'].')</td><td>'.build_select($sel).'</td></tr>'."\n";
  $disabled = ($settings['SOCKS_EXT_ENABLED'] === 'no') ? 'disabled' : ''; //disable input fields when DHCP is set
  $GLOB_disp_network_default_fields .= 'SOCKS_EXT_PORT,';
  $disp_body .= '<tr><td>Listen Port</td><td><input '.$disabled.' type="text" id="SOCKS_EXT_PORT" name="SOCKS_EXT_PORT" value="'.htmlspecialchars($settings['SOCKS_EXT_PORT']).'"></td></tr>'."\n";
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";


  $GLOB_disp_network_default_fields .= 'SOCKS_INT_ENABLED,';
  $sel = array(
          'id' => 'SOCKS_INT_ENABLED',
          'selected' => $settings['SOCKS_INT_ENABLED'],
          'onchange' => "toggle(this, 'SOCKS_INT_PORT,SOCKS_INT_FROM,SOCKS_INT_TO,SOCKS_INT_FROM_PORTRANGE', 'no', 'disabled', '', '');",
          array( 'no', 'disabled'),
          array( 'yes', 'enabled')
        );
  $disp_body .= '<tr><td>VM LAN ('.$settings['IF_INT'].')</td><td>'.build_select($sel).'</td></tr>'."\n";
  $disabled = ($settings['SOCKS_INT_ENABLED'] === 'no') ? 'disabled' : ''; //disable input fields when DHCP is set
  $GLOB_disp_network_default_fields .= 'SOCKS_INT_PORT,';
  $disp_body .= '<tr><td>Listen Port</td><td><input '.$disabled.' type="text" id="SOCKS_INT_PORT" name="SOCKS_INT_PORT" value="'.htmlspecialchars($settings['SOCKS_INT_PORT']).'"></td></tr>'."\n";
/*  $GLOB_disp_network_default_fields .= 'SOCKS_INT_FROM,';
  $disp_body .= '<tr><td>Allow network from</td><td><input '.$disabled.' type="text" id="SOCKS_INT_FROM" name="SOCKS_INT_FROM" value="'.htmlspecialchars($settings['SOCKS_INT_FROM']).'"></td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'SOCKS_INT_TO,';
  $disp_body .= '<tr><td>Allow network to</td><td><input '.$disabled.' type="text" id="SOCKS_INT_TO" name="SOCKS_INT_TO" value="'.htmlspecialchars($settings['SOCKS_INT_TO']).'"></td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'SOCKS_INT_FROM_PORTRANGE,';
  $disp_body .= '<tr><td>Forward port range</td><td><input '.$disabled.' type="text" id="SOCKS_INT_FROM_PORTRANGE" name="SOCKS_INT_FROM_PORTRANGE" value="'.htmlspecialchars($settings['SOCKS_INT_FROM_PORTRANGE']).'"></td></tr>'."\n";
*/

  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

  $disp_body .= "</table>\n";
  $disp_body .= '<input type="submit" name="store settings" value="Store Settings">';
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

  $disp_body .= '<div class="box options">';
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

  $disp_body .= '<div class="box options">';
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

      $ovpn = VPN_get_connections('MYVPN['.$x.']', array( 'selected' => $settings['MYVPN['.$x.']'], array( '', ' ')) ); //empty array creates a space between the default selection
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

  $disp_body .= '<div class="box options">';
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

function disp_general_box(){
  global $_settings;
  global $GLOB_disp_network_default_fields;

  $settings = $_settings->get_settings();
  $disp_body = '';

  $disp_body .= '<div class="box options">';
  $disp_body .= '<h2>General Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  $disp_body .= build_providers();
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

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

/*  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'NETWORK_MAX_SPEED,';
  $sel = array(
            'id' => 'NETWORK_MAX_SPEED',
            'selected' =>  $settings['NETWORK_MAX_SPEED'],
            array( '0', 'no limit'),
            array( '1600', '200KB/s'),
            array( '3200', '400KB/s'),
            array( '4800', '600KB/s'),
            array( '6400', '800KB/s'),
            array( '8192', '1MB/s'),
            array( '12288', '1.5MB/s'),
            array( '16384', '2MB/s'),
            array( '20480', '2.5MB/s'),
            array( '24576', '3MB/s'),
            array( '32768', '4MB/s'),
            array( '40960', '5MB/s'),
            array( '81920', '10MB/s'),
            array( '819200', '100MB/s')
          );
  $disp_body .= '<tr><td><strong>Experimental</strong><br>Limit network throughput<br></td><td>'.build_select($sel).'</td></tr>'."\n";
*/

  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disp_body .= '<tr><td>Web-UI Username</td><td><input type="text" name="WEB_UI_USER" value="'.htmlspecialchars($settings['WEB_UI_USER']).'"></td></tr>'."\n";
  $disp_body .= '<tr><td>Web-UI Password</td><td><input type="password" name="WEB_UI_PASSWORD" value="" placeholder="*********"></td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'WEB_UI_COOKIE_LIFETIME,';
  $disp_body .= '<tr><td>Remember Me for</td><td><input type="text" class="short" name="WEB_UI_COOKIE_LIFETIME" value="'.htmlspecialchars($settings['WEB_UI_COOKIE_LIFETIME']).'"> days</td></tr>'."\n";

  $GLOB_disp_network_default_fields .= 'WEB_UI_REFRESH_TIME,';
  $sel = array(
            'id' => 'WEB_UI_REFRESH_TIME',
            'selected' =>  $settings['WEB_UI_REFRESH_TIME'],
            array( '5000', '5 seconds'),
            array( '10000', '10 seconds'),
            array( '15000', '15 seconds'),
            array( '30000', '30 seconds'),
            array( '60000', '60 seconds')
          );
  $disp_body .= '<tr><td>Refresh Overview every </td><td>'.build_select($sel).'</td></tr>'."\n";


  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_firewall" value="Restart Firewall">';
  $disp_body .= '</div>';

  return $disp_body;
}


function disp_firewall_box(){
  global $_settings;
  global $GLOB_disp_network_default_fields;

  $settings = $_settings->get_settings();
  $disp_body = '';

  $disp_body .= '<div class="box options">';
  $disp_body .= '<h2>Firewall Settings</h2>'."\n";
  $disp_body .= "<table>\n";

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
  $disp_body .= '<tr><td><span title="incoming on port 80">Allow webUI access on</span></td><td><span title="incoming on port 80">'.   build_checkbox($sel).'</span></td></tr>'."\n";


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
  $disp_body .= '<tr><td><span title="incoming on port 22">Allow SSH on</span></td><td><span title="incoming on port 22">'.build_checkbox($sel).'</span></td></tr>'."\n";


  $use = 'FIREWALL_IF_SNMP';
  $GLOB_disp_network_default_fields .= 'FIREWALL_IF_SNMP,';
  $fw_ssh = $_settings->get_settings_array($use);
  $sel = array(
            'id' => $use,
            'selected' =>  $fw_ssh,
            array( 'FIREWALL_IF_SNMP[0]', 'eth0'),
            array( 'FIREWALL_IF_SNMP[1]', 'eth1')
          );
  $disp_body .= '<tr><td><span title="incoming on port 161 and outgoing on 162">Allow SNMP on</span></td><td><span title="incoming on port 161 and outgoing on 162">'.build_checkbox($sel).'</span></td></tr>'."\n";

  $use = 'FIREWALL_IF_SECSNMP';
  $GLOB_disp_network_default_fields .= 'FIREWALL_IF_SECSNMP,';
  $fw_ssh = $_settings->get_settings_array($use);
  $sel = array(
            'id' => $use,
            'selected' =>  $fw_ssh,
            array( 'FIREWALL_IF_SECSNMP[0]', 'eth0'),
            array( 'FIREWALL_IF_SECSNMP[1]', 'eth1')
          );
  $disp_body .= '<tr><td><span title="incoming on port 10161 and outgoing on 10162">Allow Secure SNMP on</span></td><td><span title="incoming on port 10161 and outgoing on 10162">'.build_checkbox($sel).'</span></td></tr>'."\n";
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disp_body .= "</table>\n";

  
  $disp_body .= 'Enter one port per field or keep empty.';
  $disp_body .= "<table>\n";
  $GLOB_disp_network_default_fields .= 'FIREWALL_EXT,';  
  $max_range = $_settings->get_array_count('FIREWALL_EXT');
  $ports = '';
  for( $x = 0 ; $x < $max_range ; ++$x ){
    if( array_key_exists('FIREWALL_EXT['.$x.']', $settings) === true && $settings['FIREWALL_EXT['.$x.']'] !== '' ){

      $ports .= '<tr><td>Port on '.$settings['IF_EXT'].'</td><td><input type="text" name="FIREWALL_EXT['.$x.']" value="'.$settings['FIREWALL_EXT['.$x.']'].'"></td></tr>'."\n";
    }
  }
  $disp_body .= '<tr><td>New Port on '.$settings['IF_EXT'].'</td><td><input type="text" name="FIREWALL_EXT['.$x.']" value=""></td></tr>'."\n";
  $disp_body .= $ports; //add existing ports below "new field"
  
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'FIREWALL_INT,';  
  $max_range = $_settings->get_array_count('FIREWALL_INT');
  $ports = '';
  for( $x = 0 ; $x < $max_range ; ++$x ){
    if( array_key_exists('FIREWALL_INT['.$x.']', $settings) === true && $settings['FIREWALL_INT['.$x.']'] !== '' ){

      $ports .= '<tr><td>Port on '.$settings['IF_INT'].'</td><td><input type="text" name="FIREWALL_INT['.$x.']" value="'.$settings['FIREWALL_INT['.$x.']'].'"></td></tr>'."\n";
    }
  }
  $disp_body .= '<tr><td>New Port on '.$settings['IF_INT'].'</td><td><input type="text" name="FIREWALL_INT['.$x.']" value=""></td></tr>'."\n";
  $disp_body .= $ports; //add existing ports below "new field"
  

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

  $disp_body .= '<div class="box options">';
  $disp_body .= '<h2>Advanced Settings</h2>'."\n";
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
  $disp_body .= '<tr><td>Forward to this IP</td><td><input type="text" '.$disabled.' id="FORWARD_IP" name="FORWARD_IP" value="'.htmlspecialchars($settings['FORWARD_IP']).'" title="Use as IP in \'DHCP Server Settings\' - \'Fixed IP\'"></td></tr>'."\n";


  //DNS
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'NAMESERVERS,';
  $disp_body .= '<tr><td>DNS 1</td><td><input type="text" name="NAMESERVERS[0]" value="'.$settings['NAMESERVERS[0]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 2</td><td><input type="text" name="NAMESERVERS[1]" value="'.$settings['NAMESERVERS[1]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 3</td><td><input type="text" name="NAMESERVERS[2]" value="'.$settings['NAMESERVERS[2]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 4</td><td><input type="text" name="NAMESERVERS[3]" value="'.$settings['NAMESERVERS[3]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";


  //encryption settings - no official support for third party client ... will have to look into it later
  // for future reference port 1196 support AES-128-CBC but not AES256-CBC
  // looking at the PIA client - I think it switches encryption after establishing a connection ... will have to investigate
  //$disp_body .= disp_encryption();
  //$disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

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
  $disp_body .= '<tr><td>Max packet loss</td><td>'.build_select($sel).'</td></tr>'."\n";

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

  $GLOB_disp_network_default_fields .= 'GIT_BRANCH,';
  $sel = array(
            'id' => 'GIT_BRANCH',
            'selected' =>  $settings['GIT_BRANCH'],
            array( 'release_php-gui', 'release_php-gui')
            ,array( $settings['GIT_BRANCH'], $settings['GIT_BRANCH'])
          );
  $disp_body .= '<tr><td>Development branch</td><td>'.build_select($sel).'</td></tr>'."\n";


  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings"> ';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_network" value="Network Restart">';
  $disp_body .= '</div>';

  return $disp_body;
}


function disp_interface(){
  global $_settings;
  global $GLOB_disp_network_default_fields;
  $settings = $_settings->get_settings();
  $disp_body = '';
  $fields = ''; //comma separate list of settings offered here

  $disp_body .= '<div class="box options">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="if_settings">';
  $disp_body .= '<h2>Interface Settings</h2>'."\n";
  $disp_body .= '<p>IPs for eth0 and eth1 can NOT be in the same range!</p>'."\n";
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

  $disp_body .= '</table>';
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings"> ';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_network" value="Network Restart">';
  $disp_body .= '</div>';

  return $disp_body;
}

/**
 * generates HTML form element for encryption settings
 */
//encryption settings - no official support for third party client ... will have to look into it later
// for future reference port 1196 support AES-128-CBC but not AES256-CBC
// looking at the PIA client - I think it switches encryption after establishing a connection ... will have to investigate
function disp_encryption(){
  global $GLOB_disp_network_default_fields;
  $html = '';

  $sel = array(
            'id' => 'VPN_ENCRYPTION',
            'selected' =>  $settings['VPN_ENCRYPTION'],
            array( 'AES-128-CBC', 'AES-128-CBC (default)'),
            array( 'AES-256-CBC', 'AES-256-CBC'),
            array( 'BF-CBC', 'Blowfish-CBC')
          );
  $html .= '<tr><td>VPN encryption</td><td>'.build_select($sel).'</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'VPN_ENCRYPTION,';


  $sel = array(
            'id' => 'VPN_AUTHENTICATION',
            'selected' =>  $settings['VPN_AUTHENTICATION'],
            array( 'SHA1', 'SHA1 (160bit) - (default)'),
            array( 'SHA256', 'SHA256 (256bit)')
          );
  $html .= '<tr><td>VPN authentication</td><td>'.build_select($sel).'</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'VPN_AUTHENTICATION,';

  $sel = array(
            'id' => 'VPN_HANDSHAKE',
            'selected' =>  $settings['VPN_HANDSHAKE'],
            array( 'RSA-2048', 'RSA 2048bit (default)'),
            array( 'RSA-3072', 'RSA 3072bit'),
            array( 'RSA-4096', 'RSA 4096bit'),
            array( 'ECC-256k1', 'ECC secp256k1 256bit'),
            array( 'ECC-256r1', 'ECC secp256r1 256bit'),
            array( 'ECC-521', 'ECC secp521r1 521bit')
          );
  $html .= '<tr><td>Handshake encryption</td><td>'.build_select($sel).'</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'VPN_HANDSHAKE,';

  $sel = array(
            'id' => 'VPN_LOGLEVEL',
            'selected' =>  $settings['VPN_LOGLEVEL'],
            array( '1', '1 (default)'),
            array( '4', '4'),
            array( '6', '6 (debug)'),
            array( '9', '9 (extreme)')
          );
  $html .= '<tr><td>VPN log verbosity</td><td>'.build_select($sel).'</td></tr>'."\n";
  $GLOB_disp_network_default_fields .= 'VPN_LOGLEVEL,';

  return $html;
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
  $disp_body .= '<div class="clear"></div>';
  $disp_body .= disp_interface();
  $disp_body .= disp_socks_box_new();
  $disp_body .= '<div class="clear"></div>';
  $disp_body .= '<p class="hidden" id="advanced_button"><a id="toggle_advanced_settings_toggle" class="button" href="" onclick="toggle_hide(\'toggle_advanced_settings\', \'toggle_advanced_settings_toggle\', \'Show Advanced Settings,Hide Advanced Settings\'); return false;">Show Advanced Settings</a></p>';
  $disp_body .= '<div class="clear"></div>';
  $disp_body .= '<div id="toggle_advanced_settings">';
  $disp_body .= disp_advanced_box();
  $disp_body .= disp_dhcpd_box_new();
  $disp_body .= disp_firewall_box();
  $disp_body .= '</div>';


  /* protect the form and input elements with a token */
  $pass = array('handle user request - update settings.conf - '.rtrim($GLOB_disp_network_default_fields, ','));
  //$pass = array('handle user request - update settings.conf');
  $tokens = $_token->pgen($pass);

  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= '<input type="hidden" id="store_fields" name="store_fields" value="'.  rtrim($GLOB_disp_network_default_fields, ',').'">';
  $disp_body .= '</form>';
  $disp_body .= '<script type="text/javascript">
    toggle_hide(\'advanced_button\', \'\', \'\');
    toggle_hide(\'toggle_advanced_settings\', \'\', \'\');</script>';

  return $disp_body;
}



function build_providers(){
  global $_settings;
  global $GLOB_disp_network_default_fields;
  $sel_head = array();

  $GLOB_disp_network_default_fields .= 'VPN_PROVIDERS,';

  $set_selp = $_settings->get_settings_array('VPN_PROVIDERS');
  $sel_head['id'] = 'VPN_PROVIDERS';
  $sel_head['selected'] = $set_selp;
  $provs = VPN_get_providers(); //get list of all possible providers as an array
  $sel_providers = array_merge($sel_head, $provs);

  return '<tr><td>VPN Providers</td><td>'.nl2br(build_checkbox($sel_providers)).'</td></tr>'."\n";
}







?>