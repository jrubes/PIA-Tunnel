<?php


/* show VM network and VPN overview */
$disp_body .= '<div><h2>Network Status</h2>';
$disp_body .= VM_get_status();
$disp_body .= "</div>";


/* offer connect and disconnect buttons */
$disp_body .= '<div><h2>Network Control</h2>';
$disp_body .= '<form class="inline">';
$disp_body .= VPN_get_entry_points();
$disp_body .= '<select>'
                .'<option value="UK London">UK London</option>'
                .'</select>'
                .'<input type="submit" name="connect_vpn" value="Connect VPN">'
                .'</form>';
$disp_body .= '<form class="inline"><input type="submit" name="connect_vpn" value="Disconnect VPN"></form>';



$disp_body .= "</div>";



















/* FUNCTIONS - move into functions file later */


/**
 * method to get a list of valid VPN connection
 */
function VPN_get_port(){
  
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
       $c = $_files->readfile('/pia/login.conf');
       if( $c !== false ){
         $c = explode( "\n", eol($c));
         $un = ( mb_strlen($c[0]) > 1 ) ? $c[0] : '';
         $pw = ( mb_strlen($c[1]) > 1 ) ? $c[1] : '';
         if( $un == '' || $pw == '' ){
           return false;
         }
         $_SESSION['login.conf'] = array( 'username' => $un , 'password' => $pw); //store for later
       }else{
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
?>