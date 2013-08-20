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
    if( array_key_exists($key, $_POST) === true && $val != $_POST[$key] ){
      $k = escapeshellarg($key);
      $v = escapeshellarg($_POST[$key]);
      exec("/pia/pia-settings $k $v");
      ++$upcnt;
      
      //$disp_body .= "$k is now $v<br>\n"; //dev stuff
    }
  }
  
  /* now update things with logic */
  if( $_POST['fw_enabled'] == 0 ){
    exec("/pia/pia-settings 'FORWARD_IP_ENABLED 'OFF'"); //disable forwarding by clearing the IP
  }
  
  if( $upcnt > 0 ){ $disp_body .= "<div class=\"feedback\">Settings updated</div>\n"; }
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
          'id' => 'FORWARD_IP_ENABLED',
          'selected' =>  $settings['FORWARD_IP_ENABLED'],
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>Enable Port Forwarding</td><td>'.build_select($sel).'</td></tr>'."\n";
  $disp_body .= '<tr><td>Forward IP</td><td><input type="text" name="FORWARD_IP" value="'.htmlspecialchars($settings['FORWARD_IP']).'"</td></tr>'."\n";

  //command line stuff
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $sel = array(
            'id' => 'VERBOSE',
            'selected' =>  'no',
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Verbose</td><td>'.build_select($sel).'</td></tr>'."\n";
  $sel = array(
            'id' => 'VERBOSE_DEBUG',
            'selected' =>  'no',
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Extra Verbose</td><td>'.build_select($sel).'</td></tr>'."\n";
  

//  foreach( $settings as $key => $val ){
//    $disp_body .= '<tr><td>'.htmlspecialchars($key).'</td><td><input type="text" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($val)."\"></td></tr>\n";
//  }
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disp_body .= "</table>\n";
  
  
  $disp_body .= '<h2>PIA Firewall &amp; Settings</h2>'."\n";
  $disp_body .= "<table>\n";  //iptables options
  
  //VM LAN segment forwarding
  $sel = array(
            'id' => 'vm_lan_enabled',
            'selected' =>  'yes',
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Enable VM LAN Segment</td><td>'.build_select($sel).'</td></tr>'."\n";
  //use public LAN segment for forwarding
  $sel = array(
            'id' => 'fw_public_lan_enabled',
            'selected' =>  'yes',
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Gateway for public LAN</td><td>'.build_select($sel).'</td></tr>'."\n";
  
  $disp_body .= "</table>\n";


  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= "</form></div>";
  return $disp_body;
}


/**
 * function to build a select element based on a source array
 * @param array $content array with following structure
 * <ul><li>['id'] = "foo"; name and id of select element created</li>
 * <li>['selected'] = "male"; Otional - specify top item from list by option value</li>
 * <li>array( 'option value', 'option display')</li>
 * <li>array( 'option value2', 'option display2')</li>
 * </ul>
 * @param boolean $double false will not list a 'selected' option twice, true will
 * @return string containing complete select element as HTMl source
 */
function build_select( &$content, $double=false ){
  $head = '<select id="'.$content['id'].'" name="'.$content['id']."\">\n";
  
  /* 'selected' is option */
  if( array_key_exists('selected', $content) === true ){
    $cnt = count($content)-2;//skip id & selected
  }else{
    $cnt = count($content)-1;//skip only id
  }
  
  /* time to build */
  $sel = '';
  $opts = '';
  for( $x=0 ; $x < $cnt ; ++$x ){
    $val = htmlspecialchars($content[$x][0]);
    $dis = htmlspecialchars($content[$x][1]);
    
    /* handle default selection */
    if( array_key_exists('selected', $content) === true 
            && $content[$x][0] === $content['selected'] ){
      $sel = "<option value=\"$val\">$dis</option>\n";
      if( $double !== false ){
        $opts .= "<option value=\"$val\">$dis</option>\n";
      }
    }else{
      $opts .= "<option value=\"$val\">$dis</option>\n";
    }
  }
    
  /* return it all */
  return $head.$sel.$opts.'</select>'; 
}

/**
 * method read /pia/login.conf into an array
 * @return array,bool array with ['name'], ['password'] OR FALSE on failure
 */
function VPN_get_settings(){
  //get username and password from file or SESSION
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