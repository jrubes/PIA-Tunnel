<?php
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
    //update user settings
    $disp_body .= update_network_settings();
    //show inout forms again
    $disp_body .= disp_network_default();
    break;

  default :
    $disp_body .= '<h2>Please select a menu option.</h2>';
}










/* FUNCTIONS - move into functions file later */

/**
 * method to update the settings.conf it will loop over the settings and check for matchin $_POSTs
 * @global object $_files
 */
function update_network_settings(){
  global $_files;
  $upcnt = 0;
  $settings = VPN_get_settings();

  $disp_body = '';
  foreach( $settings as $key => $val ){
    $hash = md5($key); //hash the key to avoid array issues with PHP
    if( array_key_exists($hash, $_POST) === true && $val != $_POST[$hash] ){

      //update setting
      $k = escapeshellarg($key);
      $v = escapeshellarg($_POST[$hash]);
      exec("/pia/pia-settings $k $v");
      //$disp_body .= "$k is now $v<br>\n"; //dev stuff
      ++$upcnt;

    }elseif( array_key_exists($hash.'_del', $_POST) === true ){
        //delete this setting from the file
        $disp_body .= "<div class=\"feedback\">delete not yet implemented</div>\n";
    }elseif( array_key_exists($hash.'_combined', $_POST) === true ){
        //store array values passed comma separated

        /* remove old values */
        $ret =  array();
        //get line numbers of current settings
        $config_value = substr($key, 0, strpos($key, '[') ); //this is the value of $key without [n]. this is used for the array name when writing it back
        exec('grep -n  "'.$config_value.'" /pia/settings.conf | cut -d: -f1', $ret); // $ret[] will contain line number with current settings

        //loop over returned values and remove the lines
        for( $x = count($ret)-1 ; $x >= 0 ; --$x ){ //go backwards or line numbers need to be adjusted
          $hhh = array();
          exec('sed "'.$ret[$x].'d" /pia/settings.conf > /pia/settings.conf.back');
          exec('mv /pia/settings.conf.back /pia/settings.conf');
          //echo 'sed "'.$ret[$x].'d" /pia/settings.conf > /pia/settings.conf'.'<br>';
          ++$upcnt;
        }

        //now add the settings back at the bottom of the file
        $values = explode(',', $_POST[$hash.'_combined']); // "combined" is comma separated so explode by it
        for( $x = 0 ; $x < count($values) ; ++$x ){ //yes count in a loop - only doing it since this is a single user script -- ohh yeah, sue me!
          //echo("echo '".$config_value.'['.$x."]=\"".$values[$x]."\"' >> '/pia/settings.conf'".'<br>');
          exec("echo '".$config_value.'['.$x."]=\"".$values[$x]."\"' >> '/pia/settings.conf'");
          ++$upcnt;
        }
    }
  }

  /* now update things with logic */
  $hash = md5('MYVPN[add]');
  if( array_key_exists($hash, $_POST) === true && $_POST[$hash] !== '' ){
    //get largest array index in settings.conf to append the new one

    $ret = array();
    exec('grep -c "MYVPN" /pia/settings.conf', $ret);
    if( $ret[0] > 0 ){ //config must always contain an entry!
      //this ia a new failover VPN so append to end of file
      $index = $ret[0];
      $val = $_POST[$hash];
      exec("echo 'MYVPN[$index]=\'$val\'' >> '/pia/settings.conf'"); //disable forwarding
    }
  }

  if( $upcnt > 0 ){ //settings have been changed
    //refresh /etc/networking/interfaces - just in case
    $foo = array();
    exec('sudo /pia/include/network_interfaces.sh', $foo);
      
    $disp_body .= "<div class=\"feedback\">Settings updated</div>\n";
  }
  
  //how about a network restart?
  if( array_key_exists('restart_network', $_POST) && $_POST['restart_network'] === 'Full Network Restart'){
    exec('sudo /pia/include/network_restart.sh');
    $disp_body .= "<div class=\"feedback\">Network restarted</div>\n";
  }
  
  unset($_SESSION['settings.conf']);
  return $disp_body;
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
  $disp_body .= '<input type="text" name="username" value="'.htmlentities($user['username']).'" min="1" required">';
  $disp_body .= '<input type="password" name="password" value="" placeholder="************" min="1" required">';
  $disp_body .= '<input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= "</form></div>";
  return $disp_body;
}

/**
 * returns the default UI for this option
 * @return string string with HTML for body of this page
 */
function disp_network_default(){
  $settings = VPN_get_settings();

  $disp_body = '';
  /* show Username and Password fields - expand this for more VPN providers */
  $disp_body .= '<div><h2>PIA Network Settings</h2>'."\n";
  $disp_body .= '<form action="/?page=config&amp;cmd=network_store&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= "<table>\n";


  //interface and network
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
  $sel = array(
          'id' => 'FORWARD_PORT_ENABLED',
          'selected' =>  $settings['FORWARD_PORT_ENABLED'],
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>Enable Port Forwarding</td><td>'.build_select($sel).'</td></tr>'."\n";
  $hash = md5('FORWARD_IP');
  $disp_body .= '<tr><td>Forward IP</td><td><input type="text" name="'.$hash.'" value="'.htmlspecialchars($settings['FORWARD_IP']).'"</td></tr>'."\n";

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
  $fw_ssh = VPN_get_settings_array($use);
    $c = count($fw_ssh);
    $t='';
    for( $x=0 ; $x < $c ; ++$x ){
      $hash = md5($use.'['.$x.']');
      #  $disp_body .= '<tr><td>Allow ssh logins on</td><td><input type="checkbox" name="ssh_enable_eth0" value="1"> eth0 <input type="checkbox" name="ssh_enable_eth1" value="1"> eth1</td></tr>'."\n";
      $t .= htmlspecialchars($settings[$use.'['.$x.']']).",";
    }
  $t = rtrim($t, ',');
  $disp_body .= '<tr><td>Allow ssh logins on</td><td><input type="text" name="'.$hash.'_combined" value="'.$t."\">\n".'</td></tr>'."\n";


  //now FIREWALL_IF_WEB options
  $use = 'FIREWALL_IF_WEB';
  $fw_ssh = VPN_get_settings_array($use);
    $c = count($fw_ssh);
    $t='';
    for( $x=0 ; $x < $c ; ++$x ){
      $hash = md5($use.'['.$x.']');
      #  $disp_body .= '<tr><td>Allow ssh logins on</td><td><input type="checkbox" name="ssh_enable_eth0" value="1"> eth0 <input type="checkbox" name="ssh_enable_eth1" value="1"> eth1</td></tr>'."\n";
      $t .= htmlspecialchars($settings[$use.'['.$x.']']).",";
    }
    $t = rtrim($t, ',');
    $disp_body .= '<tr><td>Allow web logins on</td><td><input type="text" name="'.$hash.'_combined" value="'.$t."\">\n".'</td></tr>'."\n";



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

  //$disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_firewall" value="Restart Firwall">';


  $disp_body .= '<h2>PIA Daemon Settings</h2>'."\n";
  $disp_body .= "<table>\n";  //iptables options

  //VM LAN segment forwarding
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
      $hash = md5('MYVPN['.$x.']').'_del';
      $disp_body .= '<tr><td>Failover '.$x.'</td><td>'.$ovpn.' <input type="checkbox" name="'.$hash.'" value="1"> delete</td></tr>'."\n";
      ++$fovers;
    }
  }
 $ovpn = VPN_get_connections('MYVPN[add]', array('initial' => 'empty'));
 $disp_body .= '<tr><td>Add Failover</td><td>'.$ovpn.'</td></tr>'."\n";


  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  
  
  
  /* system settings */
  $disp_body .= '<h2>VM System Settings</h2>'."\n";
  $disp_body .= "<table>\n";  //iptables options
  
  //eth0
  $disabled = ($settings['IF_ETH0_DHCP'] === 'yes') ? 'disabled' : ''; //disable input fields when DHCP is set
  $sel = array(
          'id' => 'IF_ETH0_DHCP',
          'selected' => $settings['IF_ETH0_DHCP'],
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>eth0 use DHCP</td><td>'.build_select($sel).'</td></tr>'."\n";
  $hash = md5('IF_ETH0_IP');
  $disp_body .= '<tr><td>eth1 IP</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH0_IP'].'"></td></tr>'."\n";
  $hash = md5('IF_ET0_SUB');
  $disp_body .= '<tr><td>eth1 Subnet</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH0_SUB'].'"></td></tr>'."\n";
  $hash = md5('IF_ETH0_GW');
  $disp_body .= '<tr><td>eth1 Gateway</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH0_GW'].'"></td></tr>'."\n";
  
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
  $hash = md5('IF_ETH1_IP');
  $disp_body .= '<tr><td>eth1 IP</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH1_IP'].'"></td></tr>'."\n";
  $hash = md5('IF_ETH1_SUB');
  $disp_body .= '<tr><td>eth1 Subnet</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH1_SUB'].'"></td></tr>'."\n";
  $hash = md5('IF_ETH1_GW');
  $disp_body .= '<tr><td>eth1 Gateway</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH1_GW'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  
  //DNS
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $hash = md5('DNS[0]');
  $disp_body .= '<tr><td>DNS 1</td><td><input type="text" name="'.$hash.'" value="'.$settings['DNS[0]'].'"></td></tr>'."\n";
  $hash = md5('DNS[1]');
  $disp_body .= '<tr><td>DNS 2</td><td><input type="text" name="'.$hash.'" value="'.$settings['DNS[1]'].'"></td></tr>'."\n";
  $hash = md5('DNS[2]');
  $disp_body .= '<tr><td>DNS 3</td><td><input type="text" name="'.$hash.'" value="'.$settings['DNS[2]'].'"></td></tr>'."\n";
  $hash = md5('DNS[3]');
  $disp_body .= '<tr><td>DNS 4</td><td><input type="text" name="'.$hash.'" value="'.$settings['DNS[3]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";  
  
  $sel = array(
          'id' => 'IF_ETH0_DHCP_SERVER',
          'selected' => $settings['IF_ETH0_DHCP_SERVER'],
          array( 'no', 'disabled'),
          array( 'yes', 'enabled')
        );
  $disp_body .= '<tr><td>DHCP server on eth0</td><td>'.build_select($sel).'</td></tr>'."\n";
  
  $sel = array(
          'id' => 'IF_ETH1_DHCP_SERVER',
          'selected' => $settings['IF_ETH1_DHCP_SERVER'],
          array( 'no', 'disabled'),
          array( 'yes', 'enabled')
        );
  $disp_body .= '<tr><td>DHCP server on eth1</td><td>'.build_select($sel).'</td></tr>'."\n";
  
  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings"> ';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_network" value="Full Network Restart">';
  
  $disp_body .= "</form></div>";
  return $disp_body;
}

/**
 * method read /pia/login.conf into an array
 * @return array,bool array with ['name'], ['password'] OR FALSE on failure
 */
function VPN_get_settings(){
  //get settings stored in settings.con
  if( array_key_exists('settings.conf', $_SESSION) !== true ){
    $ret = load_settings();
    if( $ret !== false ){
      return $ret;
    }
  }
  return $_SESSION['settings.conf'];
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