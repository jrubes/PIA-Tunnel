<?php
unset($_SESSION['ovpn']); //dev
unset($_SESSION['settings.conf']);

/* load list of available connections into SESSION */
if(array_key_exists('ovpn', $_SESSION) !== true ){
  if( VPN_ovpn_to_session() !== true ){
    echo "FATAL ERROR: Unable to get list of VPN connections!";
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
  $settings = VPN_get_settings();

  $disp_body = '';
  foreach( $settings as $key => $val ){
    if( array_key_exists($key, $_POST) === true && $val != $_POST[$key] ){
      $k = escapeshellarg($key);
      $v = escapeshellarg($_POST[$key]);
      //exec('/pia/pia-settings $k $v');
      $disp_body .= "$k is now $v<br>\n";
    }
  }
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
        $ret .= "<p>Username updated</p>";
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
        $ret .= "<p>Password updated</p>";
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
  $disp_body .= '<tr><td>IF_EXT</td><td><select name="IF_EXT"><option value="eth0">eth0</option><option value="eth1">eth1</option><option value="tun0">tun0</option></select></td></tr>'."\n";
  $disp_body .= '<tr><td>IF_INT</td><td><select name="IF_INT"><option value="eth1">eth1</option><option value="eth0">eth0</option><option value="tun0">tun0</option></select></td></tr>'."\n";
  $disp_body .= '<tr><td>IF_TUNNEL</td><td><select name="IF_TUNNEL"><option value="tun0">tun0</option><option value="eth0">eth0</option><option value="eth1">eth1</option></select></td></tr>'."\n";
  $disp_body .= '<tr><td>Forward IP</td><td><input type="text" name="FORWARD_IP" value="'.htmlspecialchars($settings['FORWARD_IP']).'"</td></tr>'."\n";

  //command line stuff
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disp_body .= '<tr><td>Verbose</td><td><select name="VERBOSE"><option value="no">no</option><option value="yes">yes</option></select></td></tr>'."\n";
  $disp_body .= '<tr><td>Extra Verbose</td><td><select name="VERBOSE_DEBUG"><option value="no">no</option><option value="yes">yes</option></select></td></tr>'."\n";

//  foreach( $settings as $key => $val ){
//    $disp_body .= '<tr><td>'.htmlspecialchars($key).'</td><td><input type="text" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($val)."\"></td></tr>\n";
//  }
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disp_body .= "</table>\n";


  $disp_body .= '<input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= "</form></div>";
  return $disp_body;
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