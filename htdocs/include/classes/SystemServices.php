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
  if( $command === 'start' )
    exec('sudo /pia/pia-forward start &>/dev/null &');
  else{
    exec('sudo /pia/pia-forward stop &>/dev/null &');
  }
}

/**
 * method to execute a full network restart
 */
function network_restart(){
  $_SESSION['connecting2'] = '';
  exec('sudo "/pia/include/network-restart.sh"');
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
  $restart = array();
  exec('sudo "/pia/include/dhcpd-start.sh"', $restart );

  $cnt = count($restart);
  if( $cnt === 1 && $restart[0] === 'Starting ISC DHCP server: dhcpd.' ){

    /* double check */
    $restart = array();
    exec('sudo "/pia/include/dhcpd-status.sh"', $restart );
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

  /* check if dhcpd is running before trying to stop it */
  $restart = array();
  exec('sudo "/pia/include/dhcpd-status.sh"', $restart );
  $cnt = count($restart);
  if( $cnt === 1 && $restart[0] === 'Status of ISC DHCP server: dhcpd is not running.' ){
    return true;
  }

  $restart = array();
  exec('sudo "/pia/include/dhcpd-stop.sh"', $restart );

  $cnt = count($restart);
  if( $cnt === 1 && $restart[0] === 'Stopping ISC DHCP server: dhcpd.' ){

    /* double check */
    $restart = array();
    exec('sudo "/pia/include/dhcpd-status.sh"', $restart );
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
 * stop dante (SOCKS 5) and verify status with service status
 * @return bool,array true on success or [0])false, [1]=error message
 */
function socks_start(){
  //this function is a quick hack which needs to match dhcpd_start() later

  /* dante does not support "service foo status" --- this is DEV - so just fucking start it :) */
  $restart = array();
  exec('sudo "/pia/include/socks-start.sh"', $restart );
  return true;

}


/**
 * stop dante (SOCKS 5) and verify status with service status
 * @return bool,array true on success or [0])false, [1]=error message
 */
function socks_stop(){
  //this function is a quick hack which needs to match dhcpd_stop() later

  /* dante does not support "service foo status" --- this is DEV - so just fucking kill it :) */
  $restart = array();
  exec('sudo "/pia/include/socks-stop.sh"', $restart );
  return true;

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
function socks_status(){
  $ret = array();
  exec('sudo "/pia/include/socks-status.sh"', $ret );

  switch( $ret[0] )
  {
    case 'pid file not found':
      return $ret[0];

    case 'running':
      return $ret[0];

    case 'not running':
      return $ret[0];

    default:
      return 'error';
  }

  return 'error';
}


/**
 * method to restart a service using NAME-restart.sh and check the return
 * @return bool,array TRUE on success or [0]=false [1]=error message
 */
function socks_restart(){

  $ret = $this->socks_stop();
  if( $ret !== true ){
    return $ret;
  }


  $ret = $this->socks_start();
  if( $ret !== true ){
    return $ret;
  }

  return true;
}



}
?>