<?php
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */

/* basic include file used for all scripts */

date_default_timezone_set('Europe/Berlin');
define('APC_CACHE_PREFIX', 'piatunnel_');
if( session_id () == '' ) session_start ();
//unset($_SESSION['settings.conf']); //DEV ONLY - remove!
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

global $meta;
$meta['title'] = 'PIA-Tunnel Management Interface'; //default prefix
$meta['name']['author'] = 'Mirko Kaiser';
$meta['name']['keywords'] = '';
$meta['name']['description'] = '';
$meta['name']['robots'] = 'NOINDEX,NOFOLLOW';
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
  global $_pia;
  $_pia->clear_session();
  exec('sudo /sbin/shutdown -h now &>/dev/null &');
}

/**
 * method to reboot the VM
 */
function VM_restart(){
  global $_pia;
  $_pia->clear_session();
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
function VPN_get_connections( $name, $build_options=array()){
  $fw_ret = array();
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
      $fw_ret[] = array( $html, '*'.$html);
    }else{
      $ret[] = array( $html, $html);
    }
  }

  if( $ret == '' ){ return false; }

  sort($ret);sort($fw_ret);
  $t = array_merge($sel, $fw_ret, $ret);
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
  $locations = get_port_forward_locations($conn_name);

  if( $locations === false ){ return false; }
  $lc = strtolower(trim($conn_name));

  foreach( $locations as $l ){
    if( strtolower($l) == $lc ){
      return true;
    }
  }
  return false;
}


/**
 * retrieve forward locations from port_forward.txt within ovpn directory
 * @param string &$conn_name connection name as stored in session
 * @return bool/array array containing one name per line or FALSE
 */
function get_port_forward_locations( &$conn_name ){
  global $_files;
  $ret = array();
  $conn_parts = explode('/', $conn_name); //is "PIAudp/Germany", need "PIAudp"
  if( $conn_parts[0] == '' ){ return false; }

  $content = $_files->readfile('/pia/ovpn/'.$conn_parts[0].'/port_forward.txt');
  if( $content === false ){ return false; }

  //build array to be returned and remove first line
  $locations = explode( "\n", eol($content));

  $cnt = count($locations);
  for( $x=1; $x < $cnt ; ++$x ){ //start at 1 to skip first line
    $ret[] = $conn_parts[0].'/'.$locations[$x];
  }

  return $ret;
}


/**
 * method read /pia/login-pia.conf into an array
 * @return array,bool array with ['name'], ['password'] OR FALSE on failure
 */
function VPN_get_user( $provider ){

  $filename = VPN_get_loginfile($provider);
  if( !preg_match("/^\/pia\/login-[a-zA-Z]{3,10}\.conf+\z/", $filename ) ){throw new Exception('FATAL ERROR: invalid login file name - '.$filename); }

  //get username and password from file or SESSION
  if( array_key_exists($filename, $_SESSION) !== true ){
    $ret = load_login($filename);
    if( $ret !== false ){
      $_SESSION[$filename] = $ret;
      return $ret;
    }else{
      return false;
    }
  }
  return $_SESSION[$filename];
}

/**
 * checks which VPN provider is currently used for the VPN connection
 * @global object $_files
 * @param string $provider hard coded string 'pia', 'frootvpn'
 * @return string/boolean containing the current provider or FALSE if file is not found
 */
function VPN_provider_connected(){
  global $_files;

  $c = $_files->readfile('/pia/cache/provider.txt');
  if( $c !== false ){
    return trim($c);
  }
  return false;
}

/**
 * checks if the login files contain two lines which are longer then 1 char
 * @param type $provider
 */
function VPN_is_provider_active( $provider ){
  $users = VPN_get_user($provider);
  
  if( $users !== false ){
    if( $provider === 'pia' ){
      if( count($_SESSION['login-pia.conf']) === 2
              && $_SESSION['login-pia.conf']['username'] != 'your PIA account name on this line')
      { return true; }else{ return false; }
    }elseif( $provider === 'frootvpn' ){
      if( count($_SESSION['login-frootvpn.conf']) === 2
          && $_SESSION['login-frootvpn.conf']['username'] != 'your FrootVPN account name on this line')
      { return true; }else{ return false; }
    }
  }
  throw new Exception('FATAL ERROR: unkown VPN provider. - '.$provider);
}



/**
 * function to load the .ovpn files into $_SESSION['ovpn'][]
 * @global array $_SESSION['ovpn']
 * @return boolean true on success or false on failure - dir does not exist
 */
function VPN_ovpn_to_session(){
  global $_settings;
  $_SESSION['ovpn'] = array();
  $providers = $_settings->get_settings_array('VPN_PROVIDERS'); //possible providers

  foreach( $providers as $set ){
    if( is_dir('/pia/ovpn/'.$set[1]) ){

      $ovpn_list = get_ovpn_list($set[1]);
      foreach( $ovpn_list as $ovpn ){
        $_SESSION['ovpn'][] = $ovpn;
      }
    }
  }

  if( count($_SESSION['ovpn']) > 0 ){ return true; }else{ return false; }
}


/**
 * retrieves list of ovpn files from directory
 */
function get_ovpn_list($provider_dir){
  global $_files;
  $ret = array();

  $tmp = array('ovpn');
  $_files->set_ls_filter($tmp, 2);
  $_files->set_ls_filter_mode('include');

  //strip .ovpn before storing in session
  $ls = $_files->ls('/pia/ovpn/'.$provider_dir);
  foreach( $ls as $val ){
    $ret[] = $provider_dir.'/'.substr($val, 0, (mb_strlen($val)-5) );
  }

  return $ret;
}


/**
 * returns list of VPN providers as an array
 */
function VPN_get_providers( ){
  $ret = array();
  $dir = "/pia/ovpn";
  $handle = opendir($dir);
  if($handle)
  {
    /* This is the correct way to loop over the directory. */
    while (false !== ($file = readdir($handle))) {
      if( $file !== '.' && $file !== '..' && is_dir($dir."/".$file) === true )
      {
        $ret[] = array( $file , $file ); //comma is correct ... I think ... some part of the code should  expect it
      }
    }
  }

  return $ret;
}


/**
   * method to display the current network info for all interfaces to the user and console
   * @param string $output='html' specifies return of the function, may be either html or array
   * @global type $CONF
   */
function VM_get_status( $output = 'html'){
  global $_settings;
  global $_pia;
  global $_services;
  $settings = $_settings->get_settings();

  $ret_arr = array();


  $sysload = get_system_load();
  $ret_arr['system_load'] = $sysload['load'];
  $ret_arr['system_mem'] = $sysload['mem'];
  $ret_arr['system_swap'] = $sysload['swap'];



  $up = $_pia->get_update_status();
  if(is_int($up) === true && $up == 0 ){
    $up_txt = '<a href="./?page=tools&amp;cid=tools&amp;cmd=update_software_client">latest release</a>';
  }elseif( $up > 0 ){
    $s = ( $up > 1 ) ? 's' : '';
    $up_txt = '<a href="/?page=tools&amp;cid=tools&amp;cmd=update_software_client">'."$up update{$s} available</a>";
  }else{
    $up_txt = $up;
  }
  $ret_arr['software_update'] = $up_txt;


  if( $_pia->status_pia_daemon() === 'running' ){
    $ret_arr['daemon_status'] = "running (autostart:{$settings['DAEMON_ENABLED']})";
  }else{
    $ret_arr['daemon_status'] = "not running (autostart:{$settings['DAEMON_ENABLED']})";
  }


  //check session.log if for current status
  $ret_arr['vpn_lbl'] = '';
  $session_status = VPN_sessionlog_status();
  switch( $session_status[0] ){
    case 'connected':
      $_SESSION['connecting2'] = ($_SESSION['connecting2'] != '') ? $_SESSION['connecting2'] : 'ERROR 5642';
      $ret_arr['vpn_status'] = "Connected to $_SESSION[connecting2]";
      $_SESSION['connecting2'] = '';

      $ret_arr['forwarding_lbl'] = 'Forwarding';
      $ret_arr['vpn_public_lbl1'] = '';
      $ret_arr['vpn_public_lbl2'] = '';

      $vpn_port = VPN_get_port();
      $vpn_ip = VPN_get_IP();

      if( $vpn_ip !== false ){
        $ret_arr['vpn_lbl'] = 'Public VPN';
        $ret_arr['vpn_public_lbl1'] = 'IP';
        $ret_arr['vpn_public_ip'] = $vpn_ip;
        $ret_arr['vpn_port'] = ($vpn_port != '') ? "$vpn_port" : '';
        $ret_arr['vpn_public_lbl2'] = ($vpn_port != '') ? 'Port' : '';

        $fw_forward_state = $_pia->check_forward_state();
        if( $fw_forward_state === true && $vpn_port != '' ){
          if( $settings['FORWARD_PORT_ENABLED'] == 'yes' ){
            $ret_arr['forwarding_port'] = "$vpn_ip:$vpn_port &lt;=&gt; $settings[FORWARD_IP]";
          }else{
            $ret_arr['forwarding_lbl'] = '';
            $ret_arr['forwarding_port'] = '';
          }
        }else{
          if( $settings['FORWARD_PORT_ENABLED'] == 'yes' && VPN_provider_connected() === 'pia' ){
            $ret_arr['forwarding_port'] = "currently disabled";

          }elseif( $settings['FORWARD_PORT_ENABLED'] == 'yes' ){
            $ret_arr['forwarding_port'] = "not supported by VPN provider";
          }else{
            $ret_arr['forwarding_lbl'] = '';
            $ret_arr['forwarding_port'] = '';
          }
        }


      }
      unset($vpn_pub);
      break;
    case 'connecting':
      $_SESSION['connecting2'] = ($_SESSION['connecting2'] != '') ? $_SESSION['connecting2'] : 'ERROR 5643';
      $ret_arr['vpn_status'] = "Connecting to $_SESSION[connecting2]";
      break;
    case 'disconnected':
      $ret_arr['vpn_status'] = "VPN Disconnected";
      $_SESSION['connecting2'] = '';
      break;
    case 'error':
      $ret_arr['vpn_status'] = "Error: $session_status[1]";
      $_SESSION['connecting2'] = 'error';
      break;
    default:
      var_dump($session_status);
  }


  //had some trouble reading status.txt right after VPN was established to I am doing it in PHP
  $ret = array();
  exec('/sbin/ip addr show '.$settings['IF_EXT'].' | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  $ret_arr['public_ip'] = $ret[0];

  $fw_forward_state = $_pia->check_forward_state($settings['IF_EXT']);
  if( $fw_forward_state === true || $settings['FORWARD_PUBLIC_LAN'] === 'yes' )
  {
    $ret_arr['public_gw'] = ( $fw_forward_state === true ) ? 'VPN Gateway enabled' : 'VPN Gateway disabled';
  }else{
    $ret_arr['public_gw'] = '';
  }

  if( $settings['SOCKS_EXT_ENABLED'] == 'yes' ){
    if( $_services->socks_status() === 'running' ){
      $ret_arr['SOCKS_EXT_ENABLED'] = "SOCKS5 Proxy on port {$settings['SOCKS_EXT_PORT']}";
    }else{
      $ret_arr['SOCKS_EXT_ENABLED'] = "SOCKS5 Proxy NOT running";
    }
  }

  $ret_arr['public_ip'] = $ret[0];
  unset($ret);

  $ret_arr['vpn_gw'] = '';
  $ret = array();
  exec('/sbin/ip addr show '.$settings['IF_INT'].' | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  if(array_key_exists('0', $ret) ){
    $ret_arr['private_ip'] = $ret[0];

    $fw_forward_state = $_pia->check_forward_state($settings['IF_INT']);
    if( $fw_forward_state === true || $settings['FORWARD_VM_LAN'] === 'yes' )
    {
      $ret_arr['vpn_gw'] = ( $fw_forward_state === true ) ? 'VPN Gateway enabled' : 'VPN Gateway disabled';
    }

    if( $settings['SOCKS_INT_ENABLED'] == 'yes' ){
      if( $_services->socks_status() === 'running' ){
        $ret_arr['SOCKS_INT_ENABLED'] = "SOCKS5 Proxy on port {$settings['SOCKS_INT_PORT']}";
      }else{
        $ret_arr['SOCKS_INT_ENABLED'] = "SOCKS5 Proxy NOT running";
      }
    }else{
      $ret_arr['SOCKS_INT_ENABLED'] = '';
    }
    $ret_arr['private_ip'] = $ret[0];
  }else{
    $ret_arr['SOCKS_INT_ENABLED'] = '';
    $ret_arr['private_ip'] = 'interface missing';
  }
  unset($ret);


  if( $output !== 'array'){
    $table = "<table border=\"0\" id=\"vm_status\"><tbody>\n";
    $table .= "<tr><td style=\"width:7em\">System</td><td>system load <span id=\"system_load\">{$sysload['load']}</span></td></tr>\n";
    $table .= "<tr><td></td><td>Mem <span id=\"system_mem\">{$sysload['mem']}</span> SWAP <span id=\"system_swap\">{$sysload['swap']}</span></td></tr>\n";
    $table .= '<tr><td>Software</td><td id="software_update">'.$up_txt.'</td></tr>';
    $table .= "<tr><td>PIA Daemon</td><td id=\"daemon_status\">{$ret_arr['daemon_status']}</td></tr>\n";
    $table .= "<tr><td>VPN Status</td><td id=\"vpn_status\">{$ret_arr['vpn_status']}</td></tr>\n";
    $table .= "<tr><td>&nbsp;</td><td></td></tr>\n";
    $table .= "<tr><td id=\"vpn_lbl\" style=\"vertical-align: top;\">{$ret_arr['vpn_lbl']}</td><td><span id=\"vpn_public_lbl1\">{$ret_arr['vpn_public_lbl1']}</span> <span id=\"vpn_public_ip\">{$ret_arr['vpn_public_ip']}</span> <span id=\"vpn_public_lbl2\">{$ret_arr['vpn_public_lbl2']}</span> <span id=\"vpn_port\">{$ret_arr['vpn_port']}</span></td></tr>\n";
    $table .= "<tr><td id=\"forwarding_lbl\" style=\"vertical-align: top;\">{$ret_arr['forwarding_lbl']}</td><td id=\"forwarding_port\">{$ret_arr['forwarding_port']}</td></tr>\n";
    $table .= "<tr><td>&nbsp;</td><td></td></tr>\n";
    $table .= "<tr><td style=\"vertical-align: top;\">Public LAN</td><td>IP <span id=\"public_ip\">{$ret_arr['public_ip']}</span></td></tr>\n";
    $table .= "<tr><td></td><td id=\"public_gw\">{$ret_arr['public_gw']}</td></tr>\n";
    $table .= "<tr><td></td><td id=\"SOCKS_EXT_ENABLED\">{$ret_arr['SOCKS_EXT_ENABLED']}</td></tr>\n";
    $table .= "<tr><td>&nbsp;</td><td></td></tr>\n";
    $table .= "<tr><td style=\"vertical-align: top;\">VM LAN</td><td>IP <span id=\"private_ip\">{$ret_arr['private_ip']}</span></td></tr>\n";
    $table .= "<tr><td></td><td id=\"vpn_gw\">{$ret_arr['vpn_gw']}</td></tr>\n";
    $table .= "<tr><td></td><td id=\"SOCKS_INT_ENABLED\">{$ret_arr['SOCKS_INT_ENABLED']}</td></tr>\n";
    $table .= "</tbody></table>\n";
  }


  if( $output === 'array'){
    return $ret_arr;
  }else{
    return $table;
  }
}

function get_system_load(){
  $ret = array();;

  //$cpu = get_cpuload();
  //$ret .= "CPU {$cpu['sy']}% usr,  {$cpu['sy']}% sys, {$cpu['id']}% idle\n";
  $cpu = sys_getloadavg();
  $ret['load'] = "$cpu[0], $cpu[1], $cpu[2]";

  $mem = get_meminfo();
  $used = $mem['total'] - $mem['free'];
  $used_swap = $mem['swap_total'] - $mem['swap_free'];
  $ret['mem'] = "{$used}/{$mem['total']}";
  $ret['swap'] =  "{$used_swap}/{$mem['swap_total']}";

  return $ret;
}


function get_cpuload(){
  $cpu = array();
  $output = shell_exec('vmstat 2>&1');
  $vmstat_array = explode("\n", $output);
  $data_line = explode(" ", $vmstat_array[2]);

  //cpu data is at the end of vmstat output
  $cnt = count($data_line)-1; //start at 0
  //var_dump($data_line);die();
  $cpu['us'] = $data_line[$cnt-5];
  $cpu['sy'] = $data_line[$cnt-3];
  $cpu['id'] = $data_line[$cnt-2];
  return $cpu;
}


/* returns the system memory info as an array */
function get_meminfo(){
  $ram_info = array();
  $output = shell_exec('cat /proc/meminfo 2>&1');
  $ram_array = explode("\n", $output);
  foreach( $ram_array as $key => $val )
  {
    //get total
    if( strstr( $val , 'MemTotal') )
    {
      $tmp = explode(':', $val);
      $tmp = trim(substr($tmp[1], 1, count($tmp[1])-3)); //strip white space and remove KB
      $ram_info['total'] = (int)round($tmp/1024); //round to full MB and store as int
    }
    //get free
    if( strstr( $val , 'MemFree') )
    {
      $tmp = explode(':', $val);
      $tmp = trim(substr($tmp[1], 1, count($tmp[1])-3)); //strip white space and remove KB
      $ram_info['free'] = (int)round($tmp/1024); //round to full MB and store as int
    }
    //get cache
    if( strstr( $val , 'Cached') && !strstr( $val , 'SwapCached') )
    {
      $tmp = explode(':', $val);
      $tmp = trim(substr($tmp[1], 1, count($tmp[1])-3)); //strip white space and remove KB
      $ram_info['cached'] = (int)round($tmp/1024); //round to full MB and store as int
    }

    //swap total
    if( strstr( $val , 'SwapTotal') )
    {
      $tmp = explode(':', $val);
      $tmp = trim(substr($tmp[1], 1, count($tmp[1])-3)); //strip white space and remove KB
      $ram_info['swap_total'] = (int)round($tmp/1024); //round to full MB and store as int
    }
    //swap free
    if( strstr( $val , 'SwapFree') )
    {
      $tmp = explode(':', $val);
      $tmp = trim(substr($tmp[1], 1, count($tmp[1])-3)); //strip white space and remove KB
      $ram_info['swap_free'] = (int)round($tmp/1024); //round to full MB and store as int
    }
    //get cache
    if( strstr( $val , 'SwapCached') )
    {
      $tmp = explode(':', $val);
      $tmp = trim(substr($tmp[1], 1, count($tmp[1])-3)); //strip white space and remove KB
      $ram_info['swap_cached'] = (int)round($tmp/1024); //round to full MB and store as int
    }
  }
  return $ram_info;
}


/**
 * function checks /pia/cache/session.log for specific words and returns an array with
 * the status at [0] and any errors at [1]
 * @global object $_files
 * @return array [0]='connected', [0]='connecting', [0]='error',[1]=message
 */
function VPN_sessionlog_status(){
  global $_files;
  global $_pia;

  $content = $_files->readfile('/pia/cache/session.log');
  if( $content == '' ){
    $content = $_files->readfile('/pia/cache/php_pia-start.log');
    if( $content == '' ){
      return array('disconnected');
    }else{
      $lines = explode("\n", $content);
      if( substr($lines[0], 0, 13) === 'connecting to' ){
        $location = substr($lines[0], strpos($content, 'connecting to')+14 ); //+13 to remove 'connecting to'
        $_SESSION['connecting2'] = $location;
        return array('connecting');
      }
    }
  }else{
    //get name of current connection and store in SESSION
    if(array_key_exists('connecting2', $_SESSION) !== true && strpos($content, 'connecting to') !== false ){
      //get name of current connection for status overview
      $lines = explode("\n", $content);
      $location = substr($lines[0], strpos($content, 'connecting to')+14); //+13 to remove 'connecting to'
      $_SESSION['connecting2'] = $location;
    }else{
      //recover from previous error
      if( array_key_exists('connecting2', $_SESSION) === true
              && ( $_SESSION['connecting2'] == '' || $_SESSION['connecting2'] === 'ERROR 5642' )
              && strpos($content, 'connecting to') !== false
        ){
        $lines = explode("\n", $content);
        $location = substr($lines[0], strpos($content, 'connecting to')+14 ); //+13 to remove 'connecting to'
        $_SESSION['connecting2'] = $location;
      }
    }

    //check for 'connected'
    if( strpos($content, 'Initialization Sequence Completed') !== false
            && strpos($content, 'TUN/TAP device tun0 opened') !== false ){
      return array('connected');

    }elseif( strpos($content, 'Received AUTH_FAILED control message') !== false ){
      //auth will sometimes fail even with correct credentials
      //will have to allow auth to fail a few times before terminating the connection attempt
      if( !isset($_SESSION['conn_auth_fail_cnt']) ){
        $_SESSION['conn_auth_fail_cnt'] = 0; //counts errors
        $_SESSION['conn_auth_perma_error'] = false; //true if error count is exceeded
      }
      if( $_SESSION['conn_auth_fail_cnt'] > 3 ){
        $_pia->clear_session();
        $_SESSION['conn_auth_fail_cnt'] = 0;
        $_SESSION['conn_auth_perma_error'] = true;
        exec('killall pia-start; /pia/include/ovpn_kill.sh; sudo /pia/pia-daemon stop &> /dev/null ; rm -rf /pia/cache/pia-daemon.log');

      }elseif( $_SESSION['conn_auth_perma_error'] === false ){
        ++$_SESSION['conn_auth_fail_cnt'];
      }
      return array('error', 'Authentication failed '.$_SESSION['conn_auth_fail_cnt'].' of 3 times. Please check your username and password.');

    }elseif( strpos($content, 'process exiting') !== false ){
      return array('disconnected');

    }elseif( strpos($content, 'TCPv4_CLIENT link remote: [AF_INET]') !== false
            || strpos($content, 'connecting to') !== false ){ //needs to be after error checks!
      return array('connecting');

    }else{
      return array('unkown status');
    }
  }

}

/**
 * returns IP of VPN. Checks session.log to get external IP for
 */
function VPN_get_IP(){
  $cmdret = array();
  exec('grep "link remote: \[AF_INET]" /pia/cache/session.log | gawk -F"]" \'{print $2}\' | gawk -F":" \'{print $1}\'', $cmdret);
  if(array_key_exists(0, $cmdret) === true && $cmdret[0] != '' ){
    return $cmdret[0];
  }

  exec('/sbin/ip -4 addr show tun0 | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $cmdret);
  if(array_key_exists(0, $cmdret) === true && $cmdret[0] != '' ){
    return $cmdret[0];
  }

  return FALSE;
}


 /**
  * having trouble reading status.txt right after connection so I am doing it myself ... grr
  * @global object $_files
  */
 function VPN_get_port(){
   global $_files;
   $cache_file = '/pia/cache/webui-port.txt';

   //check if we are connected yet
  $session_status = VPN_sessionlog_status();
  if( $session_status[0] != 'connected'){
    return 'not connected yet';
  }

  if( supports_forwarding(trim($_SESSION['connecting2'])) === false ){return false; }


  //check if the port cache should be considered old
  $session_settings_timeout = strtotime('-5 minutes'); //time until session expires
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
      if( trim($cont[0]) < $expires ){
        $pia_ret = get_port();
        if( $pia_ret !== false && array_key_exists('port', $pia_ret) ){
          settype($pia_ret['port'], 'integer');
        }

      }else{
        $pia_ret['port'] = (int)trim($cont[1]);
      }
    }else{
      $pia_ret = get_port();
      if( $pia_ret !== false && array_key_exists('port', $pia_ret) ){
        settype($pia_ret['port'], 'integer');
      }
    }

    if( is_int($pia_ret['port']) === true && $pia_ret['port'] > 0 && $pia_ret['port'] < 65536 ){
      $_SESSION['PIA_port'] = $pia_ret['port']; //needs to be refreshed later on
      $_SESSION['PIA_port_timestamp'] = strtotime('now');

      //update cache
      $txt = strtotime('now').'|'.$pia_ret['port'];
      $_files->writefile($cache_file, $txt);
    }elseif( is_array($pia_ret) === false && $pia_ret === false ){
      if( supports_forwarding($_SESSION['connecting2']) === true ){
        //unable to get port info - PIA may be down
        $_SESSION['PIA_port'] = "ERROR: getting port info. is the website up?";
        $_SESSION['PIA_port_timestamp'] = strtotime('now');
      }else{
        $_SESSION['PIA_port'] = "";
        $_SESSION['PIA_port_timestamp'] = strtotime('now');
      }
    }else{
      return false;
    }
  }

  return $_SESSION['PIA_port'];
 }

/**
 * get forwarded port from PIA
 * @global object $_files
 * @return int,boolean integer with port number or boolean false on failure
 */
function get_port(){
  global $_files;
  if( $_SESSION['connecting2'] == '' ){ return ''; }

  //get provider from connectin2
  $provider = explode('/', $_SESSION['connecting2']);
  $filename = VPN_get_loginfile($provider[0]);
  if( !preg_match("/^\/pia\/login-[a-zA-Z]{3,10}\.conf+\z/", $filename ) ){throw new Exception('FATAL ERROR: invalid login file name - '.$filename); }


  //get username and password from file or SESSION
  if( array_key_exists($filename, $_SESSION) !== true ){
    if( load_login($filename) === false ){ return false; }
  }

  //get the client ID
  if( array_key_exists('client_id', $_SESSION) !== true ){
    $c = $_files->readfile('/pia/client_id');
    if( $c !== false ){
      if( mb_strlen($c) < 1 ){ return false; }
      $_SESSION['client_id'] = $c; //store for later

    }else{
      return false;
    }
  }

  $PIA_UN = urlencode($_SESSION[$filename]['username']);
  $PIA_PW = urlencode($_SESSION[$filename]['password']);
  $PIA_CLIENT_ID = urlencode(trim($_SESSION['client_id']));
  $ret = array();
  exec('/sbin/ip addr show tun0 2>/dev/null | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  if( array_key_exists( '0', $ret) !== true ){
    //VPN  is down, can not continue to check for open ports
    return false;
  }else{
    $TUN_IP = $ret[0];
  }

  //combine vars to submit as avPOST request
  $post_vars = "user=$PIA_UN&pass=$PIA_PW&client_id=$PIA_CLIENT_ID&local_ip=$TUN_IP";


  // setup cURL resource
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://www.privateinternetaccess.com/vpninfo/port_forward_assignment');
  curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, count(explode('&', $post_vars)));
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
  curl_setopt($ch, CURLOPT_TIMEOUT, 4); //max runtime for CURL
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4); //only the connection timeout


  $curl_timeout = strtotime('-10 minutes'); //time until timeout lock expires
  if( array_key_exists('PIA_port_timeout', $_SESSION) === true ){
    //validate time
    if( $_SESSION['PIA_port_timeout'] < $curl_timeout ){
      //echo 'debug: ran curl after timeout';
      $curl_ret = curl_exec($ch);
    }else{
      //echo 'debug: lock still good';
      return false;
    }
  }else{
    //echo 'debug: ran curl';
    $curl_ret = curl_exec($ch);
  }
  if( $curl_ret === false ){
    //timeout or connection error, preventing retrying with every request
    //echo 'debug: curl failed, setting timeout';
    $_SESSION['PIA_port_timeout'] = strtotime('now');
  }else{
    //worked
    if( array_key_exists('PIA_port_timeout', $_SESSION) === true ){ unset($_SESSION['PIA_port_timeout']); }
  }

  // grab URL and pass it to the browser
  $return = json_decode(curl_exec($ch), true);
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
 * this function loads the loginf file into session and returns it
 * ['username']
 * ['password']
 * @global object $_files
 * @return array,boolean or false on failure
 */
function load_login( $filename ){
  global $_files;

  if( array_key_exists( $filename, $_SESSION) === true
          && $_SESSION[$filename]['username'] != 'your PIA account name on this line'
          && $_SESSION[$filename]['username'] != 'your FrootVPN account name on this line' )
  {
    return $_SESSION[$filename];

  }else{
    $c = $_files->readfile($filename);
    if( $c !== false ){
      $c = explode( "\n", eol($c));
      $un = ( mb_strlen($c[0]) > 0 ) ? $c[0] : '';
      $pw = ( mb_strlen($c[1]) > 0 ) ? $c[1] : '';
      if( $un == '' || $pw == '' ){
        return false;
      }
      $_SESSION[$filename] = array( 'username' => $un , 'password' => $pw); //store for later
      return $_SESSION[$filename];
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
 * <li>['selected'] = "male"; Optional - specify top item from list by option value</li>
 * <li>['onchange'] = "foo();" Optional - add onclick to \<select\>tag</li>
 * <li>['disabled'] = "" Optional - disables the select by default
 * <li>array( 'option value', 'option display')</li>
 * <li>array( 'option value2', 'option display2')</li>
 * </ul>
 * @param boolean $double false will not list a 'selected' option twice, true will
 * @return string containing complete select element as HTMl source
 */
function build_select( &$content, $double=false ){

  $hash = $content['id'];//md5($content['id']); //hash this to avoid problems with MYVPN[0] and PHP
  $onchange = ( array_key_exists('onchange', $content) === true ) ? 'onchange="'.$content['onchange'].'"' : '';
  $disabled = ( array_key_exists('disabled', $content) === true ) ? 'disabled' : '';
  $head = '<select id="'.$hash.'" name="'.$hash."\" $onchange $disabled>\n";

  /* find out how many options need to be skipped */
  $cnt = count($content) - 1; //skip ['id']
  if( array_key_exists('selected', $content) === true ){ --$cnt;}
  if( array_key_exists('initial', $content) === true ){ --$cnt;}
  if( array_key_exists('onchange', $content) === true ){ --$cnt;}
  if( array_key_exists('disabled', $content) === true ){ --$cnt;}


  /* first line empty or filled */
  if( array_key_exists('initial', $content) === true && $content['initial'] === 'empty' ){
    $head .= '<option value="">&nbsp;</option>';
  }elseif( array_key_exists('initial', $content) === true ){
    $head .= '<option value="">'.$content['initial'].'</option>';
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
    $opts .= "<input $checked type=\"checkbox\" name=\"{$content['id']}[$x]\" value=\"$dis\">$dis\n";
  }

  /* return it all */
  return $sel.$opts;
}


/**
 * retrieves the name of the login file from one of the providers .ovpn files
 * @param type $VPN_provider
 * @return boolean|array
 * @throws Exception
 */
function VPN_get_loginfile($VPN_provider){
  if( !preg_match("/^[a-zA-Z]{3,10}+\z/", $VPN_provider ) ){ throw new Exception('FATAL ERROR: invalid vpn_provider by user.'); }

  $ovpns = get_ovpn_list($VPN_provider);
  if( !array_key_exists(0, $ovpns) ){ return FALSE; }

  //pick first one of the ovpn files to get "auth-user-pass" setting
  $inj = escapeshellarg('/pia/ovpn/'.$ovpns[0].'.ovpn');
  $cmdret = array();
  exec('grep "auth-user-pass" '.$inj.' | gawk -F" " \'{print $2}\' ', $cmdret);
  $login_file = $cmdret[0];
  if( !preg_match("/^\/pia\/login-[a-zA-Z]{3,10}\.conf+\z/", $login_file ) ){throw new Exception('FATAL ERROR: invalid login file name retrieved'); }

  return $login_file;
}


/**
 * method to update username and password passed via POST
 * @global object $_files
 * @return string HTML success message or empty when there was no update
 */
function update_user_settings(){
  global $_files;
  $ret = '';

  if( !array_key_exists('vpn_provider', $_POST) ){throw new Exception('FATAL ERROR: vpn_provider not set'); }

  //get name of login file
  $login_file = VPN_get_loginfile($_POST['vpn_provider']);

  $tmp = explode('/', $login_file); $session = $tmp[1]; //session requires no path
  $username = ( array_key_exists('username', $_POST) ) ? $_POST['username'] : '';
  $password = ( array_key_exists('password', $_POST) ) ? $_POST['password'] : '';


  //create an empty file and set permissions
  if( !file_exists($login_file) ){
    file_put_contents($login_file, ' ');
    chmod($login_file, 0660); //r+w for owner and group
    @chown($login_file, 'root'); //this line should not work due to security restrictions
    chgrp($login_file, 'vpnvm');
  }

  //can not handle empty values right now ... but there is a reset command
  if( $username != '' ){
    if( file_exists($login_file) ){
      $c = $_files->readfile($login_file);
      $ct = explode( "\n", eol($c));
      if( $username !== $ct[0] ){
        $content = "$username\n$ct[1]"; //write new username with old password
        $_files->writefile($login_file, $content); //back to login-pia.conf
        $ret .= "<div id=\"feedback\" class=\"feedback\">Username updated</div>\n";
        unset($_SESSION[$session]);
        unset($_SESSION[$login_file]);
      }
    }
  }
  if( $password != '' ){
    if( file_exists($login_file) ){
      $c = $_files->readfile($login_file);
      $ct = explode( "\n", eol($c));
      if( $password !== $ct[1] ){
        $content = "$ct[0]\n$password"; //write old username with new password
        $_files->writefile($login_file, $content); //back to login-pia.conf
        $ret .= "<div id=\"feedback\" class=\"feedback\">Password updated</div>\n";
        unset($_SESSION[$session]);
        unset($_SESSION[$login_file]);
      }
    }
  }

  return $ret;
}
?>