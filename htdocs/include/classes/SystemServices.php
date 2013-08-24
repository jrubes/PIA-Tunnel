<?php
/**
 * class to control system services for PIA Tunnel
 *
 * @author Mirko Kaiser
 */
class SystemServices {


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



}
?>