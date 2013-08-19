<?php
/* basic include file used for all scripts */

date_default_timezone_set('Europe/Berlin');
define('APC_CACHE_PREFIX', 'piatunnel_');
if( session_id () == '' ) session_start ();
if( !array_key_exists('page', $_REQUEST) ){ $_REQUEST['page'] = ''; }
if( !array_key_exists('cmd', $_REQUEST) ){ $_REQUEST['cmd'] = ''; }
if( !array_key_exists('cid', $_GET) ){ $_GET['cid'] = ''; }


require_once $inc_dir.'class_loader.php';
require_once $inc_dir.'classes/class_files/class_files.php';

/* prepare global objects */
$_files = loader::loadFiles();

$header_type = 'foo'; //Change this later to add more headers
$body_type = 'foo'; //Use to select different code later
$footer_type = 'foo';
$disp_header = '';
$disp_body = '';
$disp_footer = '';
$language = 'eng';

$meta['title'] = 'PIA-Tunnel Management Interface'; //default prefix
$meta['name']['author'] = 'Mirko Kaiser';
$meta['name']['keywords'] = '';
$meta['name']['description'] = '';
$meta['name']['robots'] = 'INDEX,FOLLOW';
$meta['name']['copyright'] = 'Mirko Kaiser';
$meta['charset'] = 'UTF-8';
$meta['icon'] = ''; //'/favicon.ico';
$meta['stylesheet'] = '/style.css'; // '/css/twoColElsLtHdr.css';
$meta['javascript'] = '';

$CONF = array();
$CONF['date_format'] = 'H:i:s'; //PHP date() format


/**
 * function to generate the main menu
 * @return string the main menu in HTML string
 */
function load_menu(){

  /* define the main menu below
   * this tends to come out of a db and go into a cache but this makes no sense here
   */
  $source = array(
      /* 'URL Name', 'URL target', 'URL ID' */
      array( 'name' => 'Overview', 'url' => '/?page=main', 'id' => ''),
      array( 'name' => 'VPN Config', 'url' => '/?page=config&amp;cmd=vpn', 'id' => 'cvpn'),
      array( 'name' => 'Network Config', 'url' => '/?page=config&amp;cmd=network', 'id' => 'cnet'),
      array( 'name' => 'Tools', 'url' => '/?page=tools', 'id' => 'tools'),
      array( 'name' => 'Logout', 'url' => '/?page=logout', 'id' => 'logout')
  );

  $selected = $_GET['cid'];
  $menu = "<div class=\"mainmenu\">\n";

  /* assemble the main menu */
  foreach( $source as $menu_entry )
  {
      /* $menu_entry['id'] must be added with ? or & - figure out which one */
      $highlight_id = ( strstr($menu_entry['url'], '?') === false ) ? "?cid=$menu_entry[id]" : "&amp;cid=$menu_entry[id]";

      $menu .= '<span';
      if( $selected == $menu_entry['id'] ){ $menu .= ' id="highlight"> '; }else{ $menu .= '>'; }
      $menu .= '<a href="'.$menu_entry['url'].$highlight_id.'">'.htmlentities($menu_entry['name']).'</a>';
      $menu .= "</span>\n";
  }

  $menu .= "</div>\n";
  $menu .= '<div style="clear:both;height:0;line-height:0;display:block;"></div>'."\n";
  return $menu;
}




function VPN_is_valid_connection($val2check){
  if(array_key_exists('ovpn', $_SESSION) !== true ){
    echo "FATAL ERROR: Unable to get list of VPN connections!";
    return false;
  }

  reset($_SESSION['ovpn']);
    foreach( $_SESSION['ovpn'] as $ovpn ){
      if( strtolower($ovpn) === strtolower($val2check) ){
        return true;
      }
    }
    return false;
}

/**
 * method to get a list of valid VPN connection
 * currently only supporting PIA so I simply index the .ovpn files
 * @return string/bool string containing HTML formated as <select> or FALSE
 */
function VPN_get_connections(){
  $ret = '';

  if(array_key_exists('ovpn', $_SESSION) !== true ){
    echo "FATAL ERROR: Unable to get list of VPN connections!";
    return false;
  }

  //loop over session to generate options
  foreach( $_SESSION['ovpn'] as $ovpn ){
    $html = htmlentities($ovpn);
    $ret .= "<option value=\"$html\">$html</option>\n";
  }

  if( $ret == '' ){ return false; }
  return "<select name=\"vpn_connections\">\n$ret</select>\n";
}

/**
 * function to load the .ovpn files into $_SESSION['ovpn'][]
 * @global array $_SESSION['ovpn']
 * @return boolean true on success or false on failure - dir does not exist
 */
function VPN_ovpn_to_session(){
  if( is_dir('/pia/ovpn') ){
    global $_files;

    $tmp = array('ovpn');
    $_files->set_ls_filter($tmp, 2);
    $_files->set_ls_filter_mode('include');

    //strip .ovpn before storing in session
    $ls = $_files->ls('/pia/ovpn');
    $ret = array();
    foreach($ls as $val ){
      $ret[] = substr($val, 0, (mb_strlen($val)-5) );
    }
    $_SESSION['ovpn'] = $ret;
    return true;
  }
  return false;

}

/**
   * method to display the current network info for all interfaces to the user and console
   * @global type $CONF
   */
function VM_get_status(){
  $ret_str = '<table id="vm_status">';


  //had some trouble reading status.txt right after VPN was established to I am doing it in PHP
  $ret = array();
  exec('/sbin/ip addr show eth0 | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  $ret_str .= "<tr><td>Internet IP</td><td>$ret[0]</td></tr>";
  unset($ret);

  $ret = array();
  exec('/sbin/ip addr show eth1 | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  $ret_str .= "<tr><td>Private IP</td><td>$ret[0]</td></tr>";
  unset($ret);

  exec('/sbin/ip addr show tun0 2>/dev/null | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  if( array_key_exists( '0', $ret) !== true ){
    $ret_str .= "<tr><td>VPN</td><td>DOWN</td></tr>";
  }else{
    $port = VPN_get_port();
    $ret_str .= "<tr><td>VPN IP</td><td>$ret[0]</td></tr>";
    $ret_str .= ($port != '') ? "<tr><td>VPN Port</td><td>$port</td></tr>" : "<tr><td>VPN Port:</td><td>not supported</td></tr>";
  }
  $ret_str .= "</table>\n";

  return $ret_str;
}

 /**
  * having trouble reading status.txt right after connection so I am doing it myself ... grr
  * @global object $_files
  */
 function VPN_get_port(){
   global $_files;

   if( array_key_exists('PIA_port', $_SESSION) !== true )
   {
      //get username and password from file or SESSION
      if( array_key_exists('login.conf', $_SESSION) !== true ){
        if( load_login() === false ){
          return false;
        }
      }

     //get the client ID
     if( array_key_exists('client_id', $_SESSION) !== true ){
       $c = $_files->readfile('/pia/client_id');
       if( $c !== false ){
         if( mb_strlen($c) < 1 ){
           return false;
         }
         $_SESSION['client_id'] = $c; //store for later
       }else{
         return false;
       }
     }



     // create a new cURL resource
     $ch = curl_init();

     $PIA_UN = urlencode($_SESSION['login.conf']['username']);
     $PIA_PW = urlencode($_SESSION['login.conf']['password']);
     $PIA_CLIENT_ID = urlencode($_SESSION['client_id']);
     $ret = array();
     exec('/sbin/ip addr show tun0 2>/dev/null | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
     if( array_key_exists( '0', $ret) !== true ){
       //VPN  is down, can not continue to check for open ports
       return false;
     }else{
       $TUN_IP = $ret[0];
     }

     $post_vars = "user=$PIA_UN&pass=$PIA_PW&client_id=$PIA_CLIENT_ID&local_ip=$TUN_IP";

     // set URL and other appropriate options
     curl_setopt($ch, CURLOPT_URL, 'https://www.privateinternetaccess.com/vpninfo/port_forward_assignment');
     curl_setopt($ch, CURLOPT_HEADER, 0);
     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
     curl_setopt($ch,CURLOPT_POST, count(explode('&', $post_vars)));
     curl_setopt($ch,CURLOPT_POSTFIELDS, $post_vars);

     // grab URL and pass it to the browser
     $return = curl_exec($ch);

     // close cURL resource, and free up system resources
     curl_close($ch);

     $pia_ret = json_decode($return, true);
     if( is_int($pia_ret['port']) === true && $pia_ret['port'] > 0 && $pia_ret['port'] < 65536 ){
       $_SESSION['PIA_port'] = $pia_ret['port']; //needs to be refreshed later on
     }else{
       return false;
     }
   }
   return $_SESSION['PIA_port'];
 }

/**
 * ensures that every string uses only \n
 * @param string $string string that may contain \r\n
 * @return string retruns string with \r\n turned to n
 */
function eol($string) {
  return str_replace("\r", "\n", str_replace("\r\n", "\r", $string) );
}

/**
 * this function loads login.conf into an array, stores it in session and return it
 * ['username']
 * ['password']
 * @return array,boolean or false on failure
 */
function load_login(){
  global $_files;

  $c = $_files->readfile('/pia/login.conf');
  if( $c !== false ){
    $c = explode( "\n", eol($c));
    $un = ( mb_strlen($c[0]) > 1 ) ? $c[0] : '';
    $pw = ( mb_strlen($c[1]) > 1 ) ? $c[1] : '';
    if( $un == '' || $pw == '' ){
      return false;
    }
    $_SESSION['login.conf'] = array( 'username' => $un , 'password' => $pw); //store for later
    return $_SESSION['login.conf'];
  }else{
    return false;
  }
}
?>