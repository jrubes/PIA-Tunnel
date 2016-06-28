<?php
/*
 * Script to retrieve the port from PIA
 */

/**
 * get forwarded port from PIA
 * @global object $_files
 * @global object $_settings
 * @return int,boolean integer with port number or boolean false on failure
 */
function get_port(){
   global $_files;
   global $_settings;
   $settings = $_settings->get_settings();

  //get username and password from file or SESSION
  if( array_key_exists('login-pia.conf', $_SESSION) !== true ){
   $tmp = explode('/', $_SESSION['connecting2']);
   $filename = VPN_get_loginfile($tmp[0]);
   if( load_login($filename) === false ){
     return false;
   }
  }

  //get the client ID
  if( array_key_exists('client_id', $_SESSION) !== true ){
    $c = $_files->readfile('/usr/local/pia/client_id');
    if( $c !== false ){
      if( mb_strlen($c) < 1 ){
        return false;
      }
      $_SESSION['client_id'] = $c; //store for later
    }else{
      return false;
    }
  }


  $PIA_UN = urlencode($_SESSION['login-pia.conf']['username']);
  $PIA_PW = urlencode($_SESSION['login-pia.conf']['password']);
  $PIA_CLIENT_ID = urlencode(trim($_SESSION['client_id']));
  $ret = array();
  if( $settings['OS_TYPE'] === 'Linux'){
    exec( $settings['CMD_IP'].' addr show '.$settings['IF_TUNNEL'].' | '.$settings['CMD_GREP'].' -w "inet" | '.$settings['CMD_GAWK'].' -F" " \'{print $2}\' | '.$settings['CMD_CUT'].' -d/ -f1', $ret);
  }else{
    exec( $settings['CMD_IP'].' '.$settings['IF_TUNNEL'].' 2>/dev/null | '.$settings['CMD_GREP'].' -w "inet" | '.$settings['CMD_GAWK'].' -F" " \'{print $2}\' | '.$settings['CMD_CUT'].' -d/ -f1', $ret);
  }
  if( array_key_exists( '0', $ret) !== true ){
    //VPN  is down, can not continue to check for open ports
    return false;
  }else{
    $TUN_IP = $ret[0];
  }
  $post_vars = "user=$PIA_UN&pass=$PIA_PW&client_id=$PIA_CLIENT_ID&local_ip=$TUN_IP";

  // setup cURL
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://www.privateinternetaccess.com/vpninfo/port_forward_assignment');
  curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, count(explode('&', $post_vars)));
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
  curl_setopt($ch, CURLOPT_TIMEOUT, 4); //max runtime for cURL
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


  $return = json_decode($curl_ret, true);
  curl_close($ch);
  return $return;
 }

?>