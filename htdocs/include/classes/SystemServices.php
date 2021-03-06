<?php
/**
 * class to control system services for PIA Tunnel
 *
 * @author Mirko Kaiser
 */
class SystemServices {





/**
 * method to execute pia-forward start/stop - control the firewall
 * @param string $command "start" or "stop"
 */
function firewall_fw( $command ){
  global $settings;
  if( $command === 'start' ){
    exec( $settings['CMD_SUDO'].' /usr/local/pia/pia-forward start &>/dev/null &');
  }else{
    exec( $settings['CMD_SUDO'].' /usr/local/pia/pia-forward stop &>/dev/null &');
  }
}

/**
 * method to execute a full network restart
 */
function network_restart(){
  global $settings;
  $_SESSION['connecting2'] = '';
  exec( $settings['CMD_SUDO'].' "/usr/local/pia/include/network-restart.sh"');
}

/**
 * method to restart dhcpd using dhcpd-restart.sh and check the return
 * @return bool,array TRUE on success or [0]=false [1]=error message
 */
function dhcpd_restart(){

  $ret = $this->dhcpd_stop();
  if( $ret !== true ){
    return $ret;
  }


  $ret = $this->dhcpd_start();
  if( $ret !== true ){
    return $ret;
  }

  return true;
}


/**
 * start dhcpd and verify status with service status
 * @return bool,array true on success or [0])false, [1]=error message
 */
function dhcpd_start(){
  global $settings;
  $restart = array();
  exec( $settings['CMD_SUDO'].' "/usr/local/pia/include/dhcpd-start.sh"', $restart );

  $cnt = count($restart);
  if( $cnt === 1 && $restart[0] === 'Starting ISC DHCP server: dhcpd.' ){

    /* double check */
    $restart = array();
    exec( $settings['CMD_SUDO'].' "/usr/local/pia/include/dhcpd-status.sh"', $restart );
    $cnt = count($restart);
    if( $cnt === 1 && $restart[0] === 'Status of ISC DHCP server: dhcpd is running.' ){
      return true;
    }else{
      $err = '';
      for( $x=0; $x < $cnt ; ++$x ){
        $err .= $restart[$x]."\n";
      }
      return array( false, $err);
    }


  }else{
    $err = '';
    for( $x=0; $x < $cnt ; ++$x ){
      $err .= $restart[$x]."\n";
    }
    return array( false, $err);
  }

}

/**
 * stop dhcpd and verify status with service status
 * @return bool,array true on success or [0])false, [1]=error message
 */
function dhcpd_stop(){
  global $settings;

  /* check if dhcpd is running before trying to stop it */
  $restart = array();
  exec( $settings['CMD_SUDO'].' "/usr/local/pia/include/dhcpd-status.sh"', $restart );
  $cnt = count($restart);
  if( $cnt === 1 && $restart[0] === 'Status of ISC DHCP server: dhcpd is not running.' ){
    return true;
  }

  $restart = array();
  exec( $settings['CMD_SUDO'].' "/usr/local/pia/include/dhcpd-stop.sh"', $restart );

  $cnt = count($restart);
  if( $cnt === 1 && $restart[0] === 'Stopping ISC DHCP server: dhcpd.' ){

    /* double check */
    $restart = array();
    exec( $settings['CMD_SUDO'].' "/usr/local/pia/include/dhcpd-status.sh"', $restart );
    $cnt = count($restart);
    if( $cnt === 1 && $restart[0] === 'Status of ISC DHCP server: dhcpd is not running.' ){
      return true;
    }else{
      $err = '';
      for( $x=0; $x < $cnt ; ++$x ){
        $err .= $restart[$x]."\n";
      }
      return array( false, $err);
    }


  }else{
    $err = '';
    for( $x=0; $x < $cnt ; ++$x ){
      $err .= $restart[$x]."\n";
    }
    return array( false, $err);
  }

}


/**
 * will enable or disable the dhcpd based on the current enable / disable settings
 * for subnet 1 and 2
 */
 function dhcpd_service_control(){
   global $settings;

    if( $settings['DHCPD_ENABLED1'] === 'no' && $settings['DHCPD_ENABLED2'] === 'no' ){
      //$this->dhcpd_service_disable();
    }else{
      //$this->dhcpd_service_enable();
    }
 }

 /**
 * disables the service from starting
 */
 function dhcpd_service_disable(){
    if( $settings['DHCPD_ENABLED1'] === 'no' && $settings['DHCPD_ENABLED2'] === 'no' ){
      $this->dhcpd_service_disable();
    }else{
      $this->dhcpd_service_enable();
    }
 }




/**
 * stop dante (SOCKS 5) and verify status with service status
 * @return bool,array true on success or [0])false, [1]=error message
 */
function socks_start(){
  global $settings;

  //try to start the process 10 times before giving up
  for( $protect = 0 ; $protect < 10 ; ++$protect ){
    $stat = $this->socks_status(false);

    if( $stat === 'not running' )
    {
      switch ($settings['SOCKS_SERVER_TYPE']){
        case 'dante':
          exec("bash -c \"{$settings['CMD_SUDO']} /usr/local/pia/include/sockd-dante-start.sh &> /dev/null &\" &>/dev/null &");
          break;
        case '3proxy':
          exec("bash -c \"{$settings['CMD_SUDO']} /usr/local/pia/include/sockd-3proxy-start.sh &> /dev/null &\" &>/dev/null &");

      }

      usleep(50000);

    }elseif( $stat === 'running' ){
      return true;

    }else{
      return array( false, 'unexpected return in socks_stop();');
    }
  }
  return array( false, 'ERROR: Unable to start the SOCKS 5 proxy server!');

}


/**
 * stop dante (SOCKS 5) and verify status with service status
 * @return bool,array true on success or [0])false, [1]=error message
 */
function socks_stop(){
  global $settings;

  //try to kill the process 10 times before giving up
  for( $protect = 0 ; $protect < 10 ; ++$protect ){
    $stat = $this->socks_status(false);

    if( $stat === 'running' )
    {
     switch ($settings['SOCKS_SERVER_TYPE']){
        case 'dante':
          exec( $settings['CMD_SUDO'].' "/usr/local/pia/include/sockd-dante-stop.sh"');
          break;
        case '3proxy':
          exec( $settings['CMD_SUDO'].' "/usr/local/pia/include/sockd-3proxy-stop.sh"');

      }
      usleep(50000);

    }elseif( $stat === 'not running' ){
      return true;

    }else{
      return array( false, 'unexpected return in socks_stop();');
    }
  }
  return array( false, 'ERROR: Unable to terminate the SOCKS 5 proxy server! A restart could fix this ;)');

}

/**
 * checks the current status of the proxy server
 * @return string possible values of return string<ul>
 *                    <li>'pid file not found'</li>
 *                    <li>'running'</li>
 *                    <li>'not running'</li>
 *                    <li>'error'</li>
 *                </ul>
 */
function socks_status( $use_cache = true ){
  static $cached = ''; //short time cache for multiple calls to this method
  global $settings;

  if( $use_cache === false || $cached === '' )
  {
    $ret = array();
    switch ($settings['SOCKS_SERVER_TYPE']){
      case 'dante':
        exec( $settings['CMD_SUDO'].' "/usr/local/pia/include/sockd-dante-status.sh"', $ret );
        break;
      case '3proxy':
        exec( $settings['CMD_SUDO'].' "/usr/local/pia/include/sockd-3proxy-status.sh"', $ret );

    }


    switch( $ret[0] )
    {
      case 'pid file not found':
        $cached = 'not running'; //do it like this for now since the pid will not exist after a reboot - this is a quikc fix
        return $cached;

      case 'running':
        $cached = 'running';
        return $ret[0];

      case 'not running':
        $cached = 'not running';
        return $ret[0];

      default:
        $cached = 'not running';
        return 'error';
    }
    return 'error';

  }else{
    return $cached;
  }
}


/**
 * method to restart a service using NAME-restart.sh and check the return
 * @return bool,array TRUE on success or [0]=false [1]=error message
 */
function socks_restart(){

  if( $this->socks_status() === 'running' )
  {
    $ret = $this->socks_stop();
    if( $ret !== true ){
      return $ret;
    }
  }


  if( $this->socks_status() === 'not running' )
  {
    $ret = $this->socks_start();
    if( $ret === true ){
      return true;
    }else{
      return $ret;
    }
  }

  return false;
}



}
?>