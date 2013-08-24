<?php
/* @var $_settings PIASettings */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */

unset($_SESSION['ovpn']); //dev
unset($_SESSION['settings.conf']);

/* load list of available connections into SESSION */
if(array_key_exists('ovpn', $_SESSION) !== true ){
  if( VPN_ovpn_to_session() !== true ){
    echo "<div class=\"feedback\">FATAL ERROR: Unable to get list of VPN connections!</div>\n";
    return false;
  }
}


//act on $CMD variable
switch($_REQUEST['cmd']){
  case 'vpn':
    $disp_body .= disp_vpn_default();
    break;
  case 'vpn_store';
    //update user settings
    $disp_body .= update_user_settings();
    //show inout forms again
    $disp_body .= disp_vpn_default();
    break;

  case 'network':
    $disp_body .= disp_network_default();
    break;

  case 'network_store';
    //restart the network or store settings
    if( array_key_exists('restart_firewall', $_POST ) === true && $_POST['restart_firewall'] != '' ){
      VPN_forward('stop');
      VPN_forward('start');
      $disp_body .= "<div class=\"feedback\">Firewall has been started</div>\n";
    }else{
      //update user settings
      $disp_body .= update_network_settings();
    }

    //show inout forms again
    $disp_body .= disp_network_default();
    break;

  case 'store_setting':
    //settings are now stored section by section.
    // this will allow me to restart the network on network changes and so on.
    // $_POST['store'] indicates which settings need to be stored

    if(array_key_exists('store', $_POST) !== true ){
      //opened by URL
      $disp_body .= disp_network_default();
      break;
    }

    switch( $_POST['store'] ){
      case 'dhcpd_settings':
        //dhcpd settings will store new settings to settings.conf
        if(array_key_exists('restart_dhcpd', $_POST) ){
          $ret = $_services->dhcpd_restart();
          if( $ret === true ){
            $disp_body .= "<div class=\"feedback\">dhcpd has been restartet.</div>\n";
          }else{
            $disp_body .= "<div class=\"feedback\">".nl2br($ret[1])."</div>\n";
          }

          $disp_body .= disp_network_default();
          break;
        }else{
          if( VPN_save_settings() === true ){
            VPN_generate_dhcpd_conf(); //create new dhcpd.conf file
            $disp_body .= "<div class=\"feedback\">Settings updated. Please restart the dhcpd process to apply your changes.</div>\n";
            $disp_body .= disp_network_default();

          }else{
            $disp_body .= "<div class=\"feedback\">Request to store settings but nothing was changed.</div>\n";
            $disp_body .= disp_network_default();
          }
        }
        break;

      case 'system_settings':
        //like default but regenerate dhcpd.conf for possilbe nameserver change
        if( VPN_save_settings() === true ){
          VPN_generate_dhcpd_conf(); //create new dhcpd.conf file
          $disp_body .= "<div class=\"feedback\">Settings updated.</div>\n";
        }else{
          $disp_body .= "<div class=\"feedback\">Request to store settings but nothing was changed.</div>\n";
        }
        $disp_body .= disp_network_default();
        break;

      default:
        if( VPN_save_settings() === true ){
          $disp_body .= "<div class=\"feedback\">Settings updated.</div>\n";
        }else{
          $disp_body .= "<div class=\"feedback\">Request to store settings but nothing was changed.</div>\n";
        }
        $disp_body .= disp_network_default();
        break;

    }
    break;

  default :
    $disp_body .= '<h2>Please select a menu option.</h2>';
}










/* FUNCTIONS - move into functions file later */


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
 * main function to store settings in /pia/settings.conf
 * loop over all settings in settings.conf and look for a matching $_POST
 * settings are md5() summed in POST to avoid
 * array issues with PHP when posting a string like FOO[0]
 * @global object $_files
 * @return boolean TRUE on success or FALSE when nothing was changed
 */
function VPN_save_settings(){
  global $_settings;
  $settings = $_settings->get_settings();
  $updated_one=false;

  //handle regular strings here
  foreach( $settings as $setting_key => $setting_val ){

    //arrays need to be stored different
    if( $_settings->is_settings_array( $setting_key ) === false )
    {
      //# Regular values for settings.conf
      if( array_key_exists($setting_key, $_POST) === true && $setting_val != $_POST[$setting_key] ){
        //setting found and setting has changed, UPDATE!
        $_settings->save_settings($setting_key, $_POST[$setting_key]);
        //echo "$setting_key is now $_POST[$setting_key]<br>\n"; //dev stuff
        $updated_one=true;
      }
    }//if( VPN_is_settings_array
  }

  // # array values for settings.conf #
  // get list of possible arrays from settings.conf
  $settings_arrays = $_settings->get_array_list();

  //loop over possible array values
  foreach( $settings_arrays as $set_array ){
    if(array_key_exists($set_array, $_POST) ){
      // the settings.conf arrays have been found in $_POST
      // let's see if any values have changed
      reset($_POST);
      $found=0;

      //post may be smaller then stored settings due to checkboxes
      //check which count is larger and use that one
      $cnt_p = count($_POST[$set_array]);var_dump(count($_POST[$set_array]));
      $cnt_s = count($_settings->get_settings_array($set_array));var_dump($_settings->get_settings_array($set_array));
      $cnt = ( $cnt_p > $cnt_s ) ? $cnt_p : $cnt_s;

      for( $x = 0 ; $x < $cnt ; ++$x ){
        $index = $set_array."[$x]";
        if( $settings[$index] !== @$_POST[$set_array][$x] ){
          //at least one setting changed, store the entire array
          $tmppost = $_settings->get_array_from_post($set_array);
          var_dump($tmppost);
          $array2store = $_settings->format_array($set_array, $tmppost);
          $_settings->save_settings_array($set_array, $array2store);
          echo "changed: $index removing $set_array<br>";
          $updated_one=true;
          ++$found;
          break; //get out of for() loop as one changed setting will update the entire array
        }else{
          ++$found;
          //echo "no changed: $index removing $set_array<br>";
        }
      }
    }
  }

  //arrays without a match are empty so remove it from config
  if( $found === 0 ){
    echo "none changed, removing all: $index removing $set_array<br>";
    $array2store=array($set_array.'[0]=""');
    $_settings->save_settings_array($set_array, $array2store);
  }


  return true;
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
  $subnet = ''; //contains assembled subnet declarations
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

  // Global Option - NAMESERVERS is an array which may contain multiple entries, loop over it
  $NAMESERVERS = VPN_get_settings_array('NAMESERVERS');
  $ins_dns = '';
  foreach( $NAMESERVERS as $DNS){
    $ins_dns .= ($ins_dns === '' ) ? $DNS : ", $DNS";
  }
  $templ = str_replace('DNSSERVER_HERE', $ins_dns, $templ, $SometimesIreallyHatePHP);

  //all done - return
  return $templ.$subnet;

}


/**
 * method to update username and password passed via POST
 * @global object $_files
 * @return string string with HTML success message or empty when there was no update
 */
function update_user_settings(){
  global $_files;

  $ret = '';
  $login_file = '/pia/login.conf';
  $username = ( array_key_exists('username', $_POST) ) ? $_POST['username'] : '';
  $password = ( array_key_exists('password', $_POST) ) ? $_POST['password'] : '';

  //can not empty values right now ... but there is a reset command
  if( $username != '' ){
    if( file_exists($login_file) ){
      $c = $_files->readfile($login_file);
      $ct = explode( "\n", eol($c));
      if( $username !== $ct[0] ){
        $content = "$username\n$ct[1]"; //write new username with old password
        $_files->writefile($login_file, $content); //back to login.conf
        $ret .= "<div class=\"feedback\">Username updated</div>\n";
      }
    }
  }
  if( $password != '' ){
    if( file_exists($login_file) ){
      $c = $_files->readfile($login_file);
      $ct = explode( "\n", eol($c));
      if( $password !== $ct[1] ){
        $content = "$ct[0]\n$password"; //write old username with new password
        $_files->writefile($login_file, $content); //back to login.conf
        $ret .= "<div class=\"feedback\">Password updated</div>\n";
      }
    }
  }
  unset($_SESSION['login.conf']);
  return $ret;
}

/**
 * returns the default UI for this option
 * @return string string with HTML for body of this page
 */
function disp_vpn_default(){
  $user = VPN_get_user();

  $disp_body = '';
  /* show Username and Password fields - expand this for more VPN providers */
  $disp_body .= '<div><h2>PIA User Settings</h2>';
  $disp_body .= 'You may update your PIA username and password below.';
  $disp_body .= '<form action="/?page=config&amp;cmd=vpn_store&amp;cid=cvpn" method="post">';
  $disp_body .= '<input type="text" name="username" value="'.htmlentities($user['username']).'">';
  $disp_body .= '<input type="password" name="password" value="" placeholder="************">';
  $disp_body .= '<input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= "</form></div>";
  return $disp_body;
}

/**
 * returns the default UI for this option
 * @global object $_settings
 * @return string string with HTML for body of this page
 */
function disp_dhcpd_box(){
  global $_settings;
  $settings = $_settings->get_settings();
  $disp_body = '';

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="dhcpd_settings">';
  $disp_body .= '<h2>DHCP Server  Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  //show two subnets
  for( $x=1 ; $x < 3 ; ++$x )
  {
    $sel = array(
            'id' => 'DHCPD_ENABLED'.$x,
            'selected' => $settings['DHCPD_ENABLED'.$x],
            array( 'no', 'disabled'),
            array( 'yes', 'enabled')
          );
    $disp_body .= '<tr><td>Subnet '.$x.'</td><td>'.build_select($sel).'</td></tr>'."\n";
    //Subnet 1 settings
    $disabled = ($settings['DHCPD_ENABLED'.$x] === 'no') ? 'disabled' : ''; //disable input fields when DHCP is set
    $disp_body .= '<tr><td>Subnet IP</td><td><input '.$disabled.' type="text" name="DHCPD_SUBNET'.$x.'" value="'.htmlspecialchars($settings['DHCPD_SUBNET'.$x]).'"></td></tr>'."\n";
    $disp_body .= '<tr><td>Subnetmask</td><td><input '.$disabled.' type="text" name="DHCPD_MASK'.$x.'" value="'.htmlspecialchars($settings['DHCPD_MASK'.$x]).'"></td></tr>'."\n";
    $disp_body .= '<tr><td>Broadcast IP</td><td><input '.$disabled.' type="text" name="DHCPD_BROADCAST'.$x.'" value="'.htmlspecialchars($settings['DHCPD_BROADCAST'.$x]).'"></td></tr>'."\n";
    $disp_body .= '<tr><td>Router/Gateway</td><td><input '.$disabled.' type="text" name="DHCPD_ROUTER'.$x.'" value="'.htmlspecialchars($settings['DHCPD_ROUTER'.$x]).'"></td></tr>'."\n";
    $disp_body .= '<tr><td>IP Range</td><td><input '.$disabled.' class="long" type="text" name="DHCPD_RANGE'.$x.'" value="'.htmlspecialchars($settings['DHCPD_RANGE'.$x]).'"></td></tr>'."\n";
    $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  }

  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_dhcpd" value="Restart dhcpd">';
  $disp_body .= '</form>';
  $disp_body .= '</div>';

  return $disp_body;
}


function disp_pia_daemon_box(){
  global $_settings;
  $settings = $_settings->get_settings();
  $disp_body = '';

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="daemon_settings">';
  $disp_body .= '<h2>PIA Daemon Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  $sel = array(
            'id' => 'DAEMON_ENABLED',
            'selected' =>  $settings['DAEMON_ENABLED'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Enable pia-daemon</td><td>'.build_select($sel).'</td></tr>'."\n";

  //Failover connection selection - offer 10 entires
  $fovers = 0;
  for( $x = 0 ; $x < 10 ; ++$x ){
    if( array_key_exists('MYVPN['.$x.']', $settings) === true ){
      $ovpn = VPN_get_connections('MYVPN['.$x.']', array( 'selected' => $settings['MYVPN['.$x.']']));
      $disp_body .= '<tr><td>Failover '.$x.'</td><td>'.$ovpn.' <input type="checkbox" name="MYVPN['.$x.']" value="1"> delete</td></tr>'."\n";
      ++$fovers;
    }
  }
  $ovpn = VPN_get_connections('MYVPN[add]', array('initial' => 'empty'));
  //$disp_body .= '<tr><td>Add Failover</td><td>'.$ovpn.'</td></tr>'."\n";


  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= '</form>';
  $disp_body .= '</div>';

  return $disp_body;
}

function disp_network_box(){
  global $_settings;
  $settings = $_settings->get_settings();
  $disp_body = '';

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="network_settings">';
  $disp_body .= '<h2>PIA Network Settings</h2>'."\n";
  $disp_body .= "<table>\n";

//basic interface and network
  $sel = array(
          'id' => 'FORWARD_PORT_ENABLED',
          'selected' =>  $settings['FORWARD_PORT_ENABLED'],
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>Enable Port Forwarding</td><td>'.build_select($sel).'</td></tr>'."\n";
  $disp_body .= '<tr><td>Forward IP</td><td><input type="text" name="FORWARD_IP" value="'.htmlspecialchars($settings['FORWARD_IP']).'"></td></tr>'."\n";

  //VM LAN segment forwarding
  $sel = array(
            'id' => 'FORWARD_VM_LAN',
            'selected' =>  $settings['FORWARD_VM_LAN'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>VPN Gateway for VM LAN</td><td>'.build_select($sel).'</td></tr>'."\n";
  //use public LAN segment for forwarding
  $sel = array(
            'id' => 'FORWARD_PUBLIC_LAN',
            'selected' =>  $settings['FORWARD_PUBLIC_LAN'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>VPN Gateway for public LAN</td><td>'.build_select($sel).'</td></tr>'."\n";

  //management stuff
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

  //these are array settings so get them first then loop over to display them
  $use = 'FIREWALL_IF_SSH';
  $fw_ssh = $_settings->get_settings_array($use);
  //Wvar_dump($fw_ssh);die();
  $sel = array(
            'id' => $use,
            'selected' =>  $fw_ssh,
            array( 'FIREWALL_IF_SSH[0]', 'eth0'),
            array( 'FIREWALL_IF_SSH[1]', 'eth1')
          );
  //$sel = array_merge($sel, $fw_ssh);
  var_dump($fw_ssh);
  $disp_body .= '<tr><td>Allow ssh logins on</td><td>'.build_checkbox($sel).'</td></tr>'."\n";


  //now FIREWALL_IF_WEB options
  $use = 'FIREWALL_IF_WEB';
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
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_firewall" value="Restart Firewall">';
  $disp_body .= '</form>';
  $disp_body .= '</div>';

  return $disp_body;
}

function disp_system_box(){
  global $_settings;
  $settings = $_settings->get_settings();
  $disp_body = '';

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="system_settings">';
  $disp_body .= '<h2>VM System Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  //interface assignment
  $sel = array(
          'id' => 'IF_EXT',
          'selected' =>  $settings['IF_EXT'],
          array( 'eth0', 'eth0'),
          array( 'eth1', 'eth1'),
          array( 'tun0', 'tun0')
        );
  $disp_body .= '<tr><td>Public LAN interface</td><td>'.build_select($sel).'</td></tr>'."\n";
  $sel = array(
          'id' => 'IF_INT',
          'selected' =>  $settings['IF_INT'],
          array( 'eth0', 'eth0'),
          array( 'eth1', 'eth1'),
          array( 'tun0', 'tun0')
        );
  $disp_body .= '<tr><td>VM LAN interface</td><td>'.build_select($sel).'</td></tr>'."\n";
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
  $sel = array(
          'id' => 'IF_ETH0_DHCP',
          'selected' => $settings['IF_ETH0_DHCP'],
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>eth0 use DHCP</td><td>'.build_select($sel).'</td></tr>'."\n";
  $disp_body .= '<tr><td>eth1 IP</td><td><input '.$disabled.' type="text" name="IF_ETH0_IP" value="'.$settings['IF_ETH0_IP'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>eth1 Subnet</td><td><input '.$disabled.' type="text" name="IF_ETH0_SUB" value="'.$settings['IF_ETH0_SUB'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>eth1 Gateway</td><td><input '.$disabled.' type="text" name="IF_ETH0_GW" value="'.$settings['IF_ETH0_GW'].'"></td></tr>'."\n";

  //eth1
  $disabled = ($settings['IF_ETH1_DHCP'] === 'yes') ? 'disabled' : ''; //disable input fields when DHCP is set
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $sel = array(
          'id' => 'IF_ETH1_DHCP',
          'selected' => $settings['IF_ETH1_DHCP'],
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>eth1 use DHCP</td><td>'.build_select($sel).'</td></tr>'."\n";
  $disp_body .= '<tr><td>eth1 IP</td><td><input '.$disabled.' type="text" name="IF_ETH1_IP" value="'.$settings['IF_ETH1_IP'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>eth1 Subnet</td><td><input '.$disabled.' type="text" name="IF_ETH1_SUB" value="'.$settings['IF_ETH1_SUB'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>eth1 Gateway</td><td><input '.$disabled.' type="text" name="IF_ETH1_GW" value="'.$settings['IF_ETH1_GW'].'"></td></tr>'."\n";

  //DNS
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 1</td><td><input type="text" name="NAMESERVERS[0]" value="'.$settings['NAMESERVERS[0]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 2</td><td><input type="text" name="NAMESERVERS[1]" value="'.$settings['NAMESERVERS[1]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 3</td><td><input type="text" name="NAMESERVERS[2]" value="'.$settings['NAMESERVERS[2]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>DNS 4</td><td><input type="text" name="NAMESERVERS[3]" value="'.$settings['NAMESERVERS[3]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

  //command line stuff
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $sel = array(
            'id' => 'VERBOSE',
            'selected' =>  $settings['VERBOSE'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Verbose</td><td>'.build_select($sel).'</td></tr>'."\n";
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
  $settings = $_settings->get_settings();

  $disp_body = '';


  $disp_body .= disp_network_box();
  $disp_body .= disp_pia_daemon_box();
  $disp_body .= disp_system_box();
  $disp_body .= disp_dhcpd_box();
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