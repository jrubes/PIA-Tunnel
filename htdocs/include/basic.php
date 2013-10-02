<?php
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */

/* basic include file used for all scripts */

date_default_timezone_set('Europe/Berlin');
define('APC_CACHE_PREFIX', 'piatunnel_');
if( session_id () == '' ) session_start ();
unset($_SESSION['settings.conf']); //DEV ONLY - remove!
if( !array_key_exists('page', $_REQUEST) ){ $_REQUEST['page'] = ''; }
if( !array_key_exists('cmd', $_REQUEST) ){ $_REQUEST['cmd'] = ''; }
if( !array_key_exists('cid', $_GET) ){ $_GET['cid'] = ''; }

require_once $inc_dir.'class_loader.php';
require_once $inc_dir.'classes/PIASettings.php';
require_once $inc_dir.'classes/PIACommands.php';
require_once $inc_dir.'classes/SystemServices.php';
require_once $inc_dir.'classes/class_files/class_files.php';
require_once $inc_dir.'classes/AuthenticateUser.php';
require_once $inc_dir.'classes/class_token.php';

/* prepare global objects */
$_files = loader::loadFiles();
$_settings = loader::PIASettings();
$_services = loader::SystemServices();
$_services= loader::SystemServices();
$_pia = loader::PIACommands();
$_auth = loader::AuthenticateUser();
$_token = loader::loadToken();

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
$meta['name']['dcterms.creator'] = 'Mirko Kaiser';
$meta['charset'] = 'UTF-8';
$meta['icon'] = ''; //'/favicon.ico';
$meta['stylesheet'] = '/style.css'; // '/css/twoColElsLtHdr.css';
$meta['javascript'][] = '/js/RequestHandler.js';
$meta['javascript'][] = '/js/pia.js';

$CONF = array();
$CONF['date_format'] = 'H:i:s'; //PHP date() format

$settings = $_settings->get_settings();
$_auth->set_cookie_lifetime($settings['WEB_UI_COOKIE_LIFETIME']);


/**
 * function to generate the main menu
 * @return string the main menu in HTML string
 */
function load_menu(){
  global $_token;

  /* get a token to protect logout */
  $pass = array( 'process user logout request' );
  $tokens = $_token->pgen( $pass );

  /* define the main menu below
   * this tends to come out of a db and go into a cache but this makes no sense here
   */
  $source = array(
      /* 'URL Name', 'URL target', 'URL ID' */
      array( 'name' => 'Overview', 'url' => '/?page=main', 'id' => ''),
      array( 'name' => 'Tools', 'url' => '/?page=tools', 'id' => 'tools'),
      array( 'name' => 'Settings', 'url' => '/?page=config&amp;cmd=network', 'id' => 'cnet'),
      array( 'name' => 'VPN Accounts', 'url' => '/?page=config&amp;cmd=vpn', 'id' => 'cvpn'),
      array( 'name' => 'Logout', 'url' => '/?page=logout&amp;token='.$tokens[0], 'id' => 'logout')
  );

  $selected = $_GET['cid'];
  $menu = "<div class=\"mainmenu\">\n";

  /* assemble the main menu */
  foreach( $source as $menu_entry )
  {
      /* $menu_entry['id'] must be added with ? or & - figure out which one */
    if( $menu_entry['id'] != '' ){
      $highlight_id = ( strstr($menu_entry['url'], '?') === false ) ? "?cid=$menu_entry[id]" : "&amp;cid=$menu_entry[id]";
    }else{
      $highlight_id = '';
    }

    $menu .= '<span';
    if( $selected == $menu_entry['id'] ){ $menu .= ' id="highlight"> '; }else{ $menu .= '>'; }
    $menu .= '<a href="'.$menu_entry['url'].$highlight_id.'">'.htmlentities($menu_entry['name']).'</a>';
    $menu .= "</span>\n";
  }

  $menu .= "</div>\n";
  $menu .= '<div style="clear:both;height:0;line-height:0;display:block;"></div>'."\n";
  return $menu;
}


/**
 * method to shutdown the VM
 */
function VM_shutdown(){
  exec('sudo /sbin/shutdown -h now &>/dev/null &');
}

/**
 * method to reboot the VM
 */
function VM_restart(){
  exec('sudo /sbin/shutdown -r now &>/dev/null &');
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
 * method to get a list of arrays contained in settings.conf
 *  use $settings[$returnFromThisFunction[0]] to get the current value from settings.conf
 * @global object $_settings
 * @return array,bool return array of names,FALSE if no arrays have been found
 * array[0] == 'name of setting'
 * array[1] == 'name of setting2'
 */
function VPN_get_array_list(){
  die('old function VPN_get_array_list');
}

/**
 * function to check if $val is alread stored in the array
 * @param array $ar the array to check in
 * @param string $val the value to look for
 * @return boolean true if $val is unique, false if not
 */
function array_is_value_unique( &$ar, $val ){
  if( !is_array($ar) ){ die('FATAL SCRIPT ERROR: parameter must be an array!'); }

  reset($ar);
  foreach( $ar as $array_val ){
    if( $array_val == $val ){
      return false;
    }
  }

  return true;
}

/**
 * method to get a list of valid VPN connection
 * currently only supporting PIA so I simply index the .ovpn files
 *  - used build_select()
 * @param string $name name and id of element as a string
 * @param array $build_options *Optional* additional build_select() as array (besides name)
 * @return string/bool string containing HTML formated as <select> or FALSE
 */
function VPN_get_connections($name, $build_options=array()){
  $ret = array();
  $sel = array();
  $sel['id'] = $name;
  if( count($build_options) > 0 ){ $sel = array_merge($sel,$build_options); }


  if(array_key_exists('ovpn', $_SESSION) !== true ){
    echo "FATAL ERROR: Unable to get list of VPN connections!";
    return false;
  }

  //loop over session to generate options
  foreach( $_SESSION['ovpn'] as $ovpn ){
    $html = htmlentities($ovpn);
    //$ret .= "<option value=\"$html\">$html</option>\n";
    if( supports_forwarding($html) === true ){
      $ret[] = array( $html, '*'.$html);
    }else{
      $ret[] = array( $html, $html);
    }
  }

  if( $ret == '' ){ return false; }

  sort($ret);
  $t = array_merge($sel, $ret);
  $assembled = build_select($t);
  //return "<select name=\"vpn_connections\">\n$ret</select>\n";
  return $assembled;
}

/**
 * check if the connection supports port forwarding
 * this is hardcoded information
 * @param string $conn_name name OVPN file without .ovpn
 */
function supports_forwarding( $conn_name ){
  $locations = array( 'Canada', 'CA Toronto', 'Switzerland', 'Sweden', 'Romania', 'Germany', 'France', 'Netherlands' );
  $lc = strtolower($conn_name);

  foreach( $locations as $l ){
    if( strtolower($l) == $lc ){
      return true;
    }
  }
  return false;
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
   * @param string $output='html' specifies return of the function, may be either html or array
   * @global type $CONF
   */
function VM_get_status( $output = 'html'){
  global $_settings;
  global $_pia;
  $settings = $_settings->get_settings();

  $ret_str = '<table id="vm_status">';
  $ret_arr = array();

  //check session.log if for current status
  $session_status = VPN_sessionlog_status();
  $ret_str .= "<tr><td style=\"width:7em\">Status</td>";
  switch( $session_status[0] ){
    case 'connected':
      $_SESSION['connecting2'] = ($_SESSION['connecting2'] != '') ? $_SESSION['connecting2'] : 'ERROR 5642';
      $ret_str .= "<td id=\"vpn_status\">Connected to $_SESSION[connecting2]</td></tr>";
      $ret_arr['vpn_status'] = "Connected to $_SESSION[connecting2]";
      break;
    case 'connecting':
      $_SESSION['connecting2'] = ($_SESSION['connecting2'] != '') ? $_SESSION['connecting2'] : 'ERROR 5642';
      $ret_str .= "<td id=\"vpn_status\">Connecting to $_SESSION[connecting2]</td></tr>";
      $ret_arr['vpn_status'] = "Connecting to $_SESSION[connecting2]";
      break;
    case 'disconnected':
      $ret_str .= "<td id=\"vpn_status\">VPN Disconnected</td></tr>";
      $ret_arr['vpn_status'] = "VPN Disconnected";
      break;
    case 'error':
      $ret_str .= "<td id=\"vpn_status\">Error: $session_status[1]</td></tr>";
      $ret_arr['vpn_status'] = "Error: $session_status[1]";
      break;
    default:
      var_dump($session_status);
  }

  if( $_pia->status_pia_daemon() === 'running' ){
    $ret_str .= "<tr><td>PIA Daemon</td><td id=\"daemon_status\">running (autostart:{$settings['DAEMON_ENABLED']})</td></tr>";
    $ret_arr['daemon_status'] = "running (autostart:{$settings['DAEMON_ENABLED']})";
  }else{
    $ret_str .= "<tr><td>PIA Daemon</td><td id=\"daemon_status\">not running (autostart:{$settings['DAEMON_ENABLED']})</td></tr>";
    $ret_arr['daemon_status'] = "not running (autostart:{$settings['DAEMON_ENABLED']})";
  }

  //had some trouble reading status.txt right after VPN was established to I am doing it in PHP
  $ret = array();
  exec('/sbin/ip addr show eth0 | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  $ret_str .= "<tr><td>Public LAN IP</td><td id=\"public_ip\">$ret[0]</td></tr>";
  $ret_arr['public_ip'] = $ret[0];
  unset($ret);

  $ret = array();
  exec('/sbin/ip addr show eth1 | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  if(array_key_exists('0', $ret) ){
    $ret_str .= "<tr><td>Private LAN IP</td><td id=\"private_ip\">$ret[0]</td></tr>";
    $ret_arr['private_ip'] = $ret[0];
  }else{
    $ret_str .= "<tr><td>Private IP</td><td id=\"private_ip\">please refresh the page</td></tr>";
    $ret_arr['private_ip'] = '';
  }
  unset($ret);

  exec('/sbin/ip addr show tun0 2>/dev/null | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  if( array_key_exists( '0', $ret) !== true ){
    $ret_str .= "<tr id=\"vpn_down\"><td>VPN</td><td>down</td></tr>";
    $ret_arr['vpn_port'] = '';
    $ret_arr['vpn_ip'] = '';
    $ret_arr['vpn_public_ip'] = '';
  }else{
    //VPN is enabled. Display info
    $port = VPN_get_port();
    //$ret_str .= "<tr><td>VPN IP</td><td id=\"vpn_ip\">$ret[0]</td></tr>";
    //$ret_arr['vpn_ip'] = $ret[0];
    $vpn_pub = array();
    exec('grep "UDPv4 link remote: \[AF_INET]" /pia/cache/session.log | gawk -F"]" \'{print $2}\' | gawk -F":" \'{print $1}\'', $vpn_pub);
    if( array_key_exists( '0', $vpn_pub) === true ){
      $ret_str .= "<tr><td>VPN Public IP</td><td id=\"vpn_public_ip\">$vpn_pub[0]</td></tr>";
      $ret_arr['vpn_public_ip'] = $vpn_pub[0];
      $ret_str .= ($port != '') ? "<tr><td>VPN Port</td><td id=\"vpn_port\">$port</td></tr>" : "<tr><td>VPN Port:</td><td>not supported by location</td></tr>";
      $ret_arr['vpn_port'] = ($port != '') ? "$port" : "not supported by location";

      //show forwarding info
      if( $settings['FORWARD_PORT_ENABLED'] == 'yes' ){
        $ret_str .= "<tr><td>Port Forwarding</td><td>$vpn_pub[0]:$port &lt;=&gt; $settings[FORWARD_IP]</td></tr>";
        $ret_arr['forwarding_port'] = "$vpn_pub[0]:$port &lt;=&gt; $settings[FORWARD_IP]";
      }
      if( $settings['FORWARD_VM_LAN'] == 'yes' ){
        $ret_str .= "<tr><td>FW interfaces</td><td>$settings[IF_INT] =&gt; $settings[IF_TUNNEL]</td></tr>";
        $ret_arr['forwarding_if1'] = "$settings[IF_INT] =&gt; $settings[IF_TUNNEL]";
      }
      if( $settings['FORWARD_PUBLIC_LAN'] == 'yes' ){
        $ret_str .= "<tr><td>FW interfaces</td><td>$settings[IF_EXT] =&gt; $settings[IF_TUNNEL]</td></tr>";
        $ret_arr['forwarding_if2'] = "$settings[IF_INT] =&gt; $settings[IF_TUNNEL]";
      }

    }else{
      $ret_str .= "<tr id=\"vpn_down\"><td>VPN</td><td>down</td></tr>";
    }
  }

  $ret_str .= "</table>\n";

  if( $output === 'array'){
    return $ret_arr;
  }else{
    return $ret_str;
  }
}


/**
 * function checks /pia/cache/session.log for specific words and returns an array with
 * the status at [0] and any errors at [1]
 * @global object $_files
 * @return array [0]='connected', [0]='connecting', [0]='error',[1]=message
 */
function VPN_sessionlog_status(){
  global $_files;

  $content = $_files->readfile('/pia/cache/session.log');
  if( $content == '' ){
    return array('disconnected');
  }else{
    //get name of current connection and store in SESSION
    if(array_key_exists('connecting2', $_SESSION) !== true && strpos($content, 'connecting to') !== false ){
      //get name of current connection for status overview
      $lines = explode("\n", $content);
      $location = substr($lines[0], strpos($content, 'connecting to')+13 ); //+13 to remove 'connecting to'
      $_SESSION['connecting2'] = $location;
    }else{
      //recover from previous error
      if( array_key_exists('connecting2', $_SESSION) === true
              && ( $_SESSION['connecting2'] == '' || $_SESSION['connecting2'] === 'ERROR 5642' )
              && strpos($content, 'connecting to') !== false
        ){
        $lines = explode("\n", $content);
        $location = substr($lines[0], strpos($content, 'connecting to')+13 ); //+13 to remove 'connecting to'
        $_SESSION['connecting2'] = $location;
      }
    }

    //check for 'connected'
    if( strpos($content, 'Initialization Sequence Completed') !== false
            && strpos($content, 'TUN/TAP device tun0 opened') !== false ){
      return array('connected');
    }elseif( strpos($content, 'Received AUTH_FAILED control message') !== false ){
      return array('error', 'Authentication error. Please check your username and password.');
    }elseif( strpos($content, 'process exiting') !== false ){
      return array('disconnected');
    }elseif( strpos($content, 'UDPv4 link remote: [AF_INET]') !== false
            || strpos($content, 'connecting to') !== false ){ //needs to be after error checks!
      return array('connecting');
    }else{
      return array('unkown status');
    }
  }

}

 /**
  * having trouble reading status.txt right after connection so I am doing it myself ... grr
  * @global object $_files
  */
 function VPN_get_port(){
   global $_files;
   $cache_file = '/pia/cache/webgui_port.txt';

   //check if we are connected yet
  $session_status = VPN_sessionlog_status();
  if( $session_status[0] != 'connected'){
    return 'not connected yet';
  }

  unset($_SESSION['PIA_port_timestamp']);

   //check if the port cache should be considered old
   $session_settings_timeout = strtotime('-300 minutes'); //time until session expires
   if( array_key_exists('PIA_port_timestamp', $_SESSION) === true ){
     //validate time
     if( $_SESSION['PIA_port_timestamp'] < $session_settings_timeout ){
      if( array_key_exists('PIA_port', $_SESSION) === true ){
        unset($_SESSION['PIA_port']); //time expired
      }
     }
   }else{
     //does not exist so destroy PIA_port just to be save
     if( array_key_exists('PIA_port', $_SESSION) === true ){
       unset($_SESSION['PIA_port']);
     }
   }


   //get fresh port info
   if( array_key_exists('PIA_port', $_SESSION) !== true )
   {
      //read from cache file or get fresh info
      if( file_exists($cache_file) === true ){
        $cont = explode('|', $_files->readfile($cache_file));

        //cont(0) is timestamp of creation
        //cont(1) contains the port number
        $expires = strtotime('-96 hours'); //time until session expires
        if( trim($cont(0)) < $expires ){
          $pia_ret = get_port();

        }else{
          $pia_ret['port'] = trim(cont(1));
        }
      }


     if( is_int($pia_ret['port']) === true && $pia_ret['port'] > 0 && $pia_ret['port'] < 65536 ){
      $_SESSION['PIA_port'] = $pia_ret['port']; //needs to be refreshed later on
      $_SESSION['PIA_port_timestamp'] = strtotime('now');

      //update cache
      $txt = strtotime('now').'|'.$pia_ret['port'];
      $_files->writefile($cache_file, $txt);
     }elseif( is_array($pia_ret) === false && $pia_ret === false ){
      //unable to get port info - PIA may be down
      $_SESSION['PIA_port'] = "ERROR: getting port info. is the website up?";
      $_SESSION['PIA_port_timestamp'] = strtotime('now');
     }else{
       return false;
     }
   }

   return $_SESSION['PIA_port'];
 }

 function get_port(){
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
  $PIA_CLIENT_ID = urlencode(trim($_SESSION['client_id']));
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
  curl_setopt($ch,CURLOPT_TIMEOUT, 10); //max runtime for CURL
  curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 4); //only the connection timeout

  // grab URL and pass it to the browser
  $return = json_decode(curl_exec($ch), true);

  // close cURL resource, and free up system resources
  curl_close($ch);

  return $return;
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
 * @global object $_files
 * @return array,boolean or false on failure
 */
function load_login(){
  global $_files;

  if( array_key_exists('login.conf', $_SESSION) === true
          && $_SESSION['login.conf']['username'] != 'your PIA account name on this line' )
  {
    return $_SESSION['login.conf'];
  }else{
    $c = $_files->readfile('/pia/login.conf');
    if( $c !== false ){
      $c = explode( "\n", eol($c));
      $un = ( mb_strlen($c[0]) > 0 ) ? $c[0] : '';
      $pw = ( mb_strlen($c[1]) > 0 ) ? $c[1] : '';
      if( $un == '' || $pw == '' ){
        return false;
      }
      $_SESSION['login.conf'] = array( 'username' => $un , 'password' => $pw); //store for later
      return $_SESSION['login.conf'];
    }else{
      return false;
    }
  }
}

/**
 * function to build a select element based on a source array
 * @param array $content array with following structure
 * <ul><li>['id'] = "foo"; name and id of select element created</li>
 * <li>['initial'] = "empty|filled"; empty to have initial selection of nothing or filled to use [0]
 * <li>['selected'] = "male"; Otional - specify top item from list by option value</li>
 * <li>array( 'option value', 'option display')</li>
 * <li>array( 'option value2', 'option display2')</li>
 * </ul>
 * @param boolean $double false will not list a 'selected' option twice, true will
 * @return string containing complete select element as HTMl source
 */
function build_select( &$content, $double=false ){

  $hash = $content['id'];//md5($content['id']); //hash this to avoid problems with MYVPN[0] and PHP
  $head = '<select id="'.$hash.'" name="'.$hash."\">\n";

  /* 'selected' is option */
  if( array_key_exists('selected', $content) === true ){
    $cnt = count($content)-2;//skip id & selected
  }else{
    $cnt = count($content)-1;//skip only id
  }
  if( array_key_exists('initial', $content) === true ){
    --$cnt; //-1 more if initial is set
  }

  /* first line empty or filled */
  if( array_key_exists('initial', $content) === true && $content['initial'] === 'empty' ){
    $head .= '<option value="">&nbsp;</option>';
  }

  /* time to build the rest */
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
 * function to build checkboxes based on a source array
 * @param array $content array with following structure
 * <ul><li>['id'] = "foo"; name and id of select element created</li>
 * <li>['selected'] = "male"; Otional - specify top item from list by option value</li>
 * <li>array( 'option value', 'option display')</li>
 * <li>array( 'option value2', 'option display2')</li>
 * </ul>
 * @param boolean $double false will not list a 'selected' option twice, true will
 * @return string containing complete checkbox set as HTMl source
 */
function build_checkbox( &$content, $double=false ){

  /* 'selected' is option */
  if( array_key_exists('selected', $content) === true ){
    $cnt = count($content)-2;//skip id & selected
  }else{
    $cnt = count($content)-1;//skip only id
  }

  /* time to build the rest */
  $sel = '';
  $opts = '';
  for( $x=0 ; $x < $cnt ; ++$x ){
    $val = htmlspecialchars($content[$x][0]);
    $dis = htmlspecialchars($content[$x][1]);

    $checked = '';
    //array keys may not match so loop over it
    if( @array_key_exists("$x", $content['selected']) === true )
    reset($content['selected']);
    foreach( $content['selected'] as $cur ){
      if( $cur[1] == $dis ){
        //echo "match $dis<br>";
        $checked = 'checked';
      }else{
        //echo "NO match '$cur[0]' vs '$val'<br>\n";
      }
    }

    /* handle default selection */
    $opts .= "<input $checked type=\"checkbox\" name=\"{$content['id']}[$x]\" value=\"$dis\">$dis</option>\n";
  }

  /* return it all */
  return $sel.$opts;
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
        $ret .= "<div id=\"feedback\" class=\"feedback\">Username updated</div>\n";
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
        $ret .= "<div id=\"feedback\" class=\"feedback\">Password updated</div>\n";
      }
    }
  }
  unset($_SESSION['login.conf']);
  return $ret;
}
?>