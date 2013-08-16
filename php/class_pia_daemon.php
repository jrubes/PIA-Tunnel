<?php

/**
 * Description of class_pia_daemon
 *
 * @author dev
 */
class PiaDaemon {
  var $socket;
  var $client_index;
  var $ovpn_array;
  private $state; //holds current state of the daemon
    /*
     * states:
     *  none - doing nothing
     *  connecting vpn - bash script will attempt to connect to vpn. need to check for an established connection now
     *  connected - VPN is/should be up
     *  checking vpn - checking if VPN is up
     *  checking internet - checking if Internet is up
     *  offline vpn - vpn is offline but internet is up
     *  offline internet - internet and vpn are down
     *  sleeping n - sleeping for n seconds
     *
     */


  function __construct() {
    $this->socket = false;
    $this->client_index = false;
    $this->state = 'none';

    $this->populate_ovpn_array();
  }

  /* pass the socket object */
  function pass_socket( &$socket ){
    $this->socket = $socket;
  }

  /**
   * reads /pia/ovpn/*.ovpn and store the conent in an array.
   * the file names will be used as the VPN name by the connect function
   */
  private function populate_ovpn_array(){
    if( is_dir('/pia/ovpn') ){
      $_files = new FilesystemOperations();

      $tmp = array('ovpn');
      $_files->set_ls_filter($tmp, 2);
      $_files->set_ls_filter_mode('include');
      $this->ovpn_array = $_files->ls('/pia/ovpn');
    }
  }

  /**
   * allows you to pass the client currently being processed in $_clients
   * @param type $client_index_id
   */
  function pass_client_index( &$client_index_id )  {
    if( is_int($client_index_id) === true ){
      $this->client_index = $client_index_id;
      return true;
    }else{ return false; }
  }

  /**
   * method to respond to user input, will find a matching case and do something
   * @param string $input info sent by user
   */
  function switch_input( &$input ){
    $daemon_cmd = strtoupper($input[0]);
    switch( $daemon_cmd )
    {
        case 'ST':    //DEBUG COMMAND
          $this->input_st();
          break;
        case 'HELP':    //List public commands
          $this->input_help();
          break;
        case 'EXIT';
          $this->input_exit();
          break;
        case 'CONNECT';
          $this->input_connect($input);
          break;
        case 'STATUS';
          $this->input_satus();
          break;
        case 'SHUTDOWN';
          $this->shutdown();
          break;
        default:
          $this->input_invalid($daemon_cmd);
          break;
    }
  }

  private function input_satus(){
    global $CONF;
    $ret = array();

    print "\n".date($CONF['date_format'])." user requested status. here is a copy for the console.";
    exec('/sbin/ip addr show eth0 | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
    $msg = "Internet IP: ".$ret[0];
    print "\n$msg";
    $this->socket->write($this->client_index, $msg);
    unset($ret);

    exec('/sbin/ip addr show eth1 | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
    $msg = "VM private IP: ".$ret[0];
    print "\n$msg";
    $this->socket->write($this->client_index, $msg);
    unset($ret);

    exec('/sbin/ip addr show tun0 2>/dev/null | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
    if( array_key_exists( '0', $ret) !== true ){
      $msg = "VPN is DOWN";
    }else{
      $msg = "VPN IP: ".$ret[0];
    }
    print "\n$msg";
    $this->socket->write($this->client_index, $msg);
    unset($ret);
  }

  /**
   * method to estabish a VPN connection
   * WARNING VPN connection must be less than 26 chars
   * @param array $input_array user supplied input after explode(" ", trim($USERINPUT));
   * @return bool true,false true when a match has been found and the connection shell script has been started
   */
  private function input_connect($input_array){
    global $_client;
    global $CONF;

    /* 0 must be connect and 1 must be a valid VPN name */
    $l = mb_strlen($input_array[1]);
    if( strtoupper($input_array[0]) === 'CONNECT' && $l > 0 && $l < 26 ){
      //check if the specified .ovpn file exists
      reset($this->ovpn_array);
      foreach( $this->ovpn_array as $ovpn ){
        if( strtolower($ovpn) === strtolower($input_array[1].'.ovpn') )
        {
          //match found! initiate a VPN connection and break
          $exec_ovpn = substr($ovpn, 0, (mb_strlen($ovpn)-5)); // -5 for .ovpn - NEVER use user supplied input when you don't have to!!!
          print "\n".date($CONF['date_format'])." Establishing a new VPN connection to $exec_ovpn";
          exec("/pia/php/shell/pia-connect \"$exec_ovpn\" > /dev/null 2>/dev/null &"); //calling my bash scripts - this should work :)
          $this->state = 'connecting '.$exec_ovpn;

          return true;
        }
      }
      print "\n".date($CONF['date_format'])." User supplied invalid VPN connection name: ".$input_array[1];
      $msg = "Invalid VPN connection name! ".$input_array[1];
      $this->socket->write($this->client_index, $msg);
      $_client[$this->client_index]['cmd_error_cnt']++;

    }else{
      print "\n".date($CONF['date_format'])." User supplied invalid VPN connection name";
      $msg = "Invalid VPN connection name!";
      $this->socket->write($this->client_index, $msg);
      $_client[$this->client_index]['cmd_error_cnt']++;
    }
  }


  /**
   * disconnect a connection
   * @global type $_client
   */
  private function input_exit(){
    global $_client;

    $msg = "Good bye.\r\n".chr(0);
    $this->socket->write($this->client_index, $msg);
    $this->socket->close($this->client_index);
    unset($_client[$this->client_index]); //Remove token since client disconnected
  }

  private function input_invalid( &$input ){
    global $_client;
    global $CONF;

    $msg = "UNKOWN COMMAND $input";
    $this->socket->write($this->client_index, $msg);
    $msg = "try HELP for a list of commands";
    $this->socket->write($this->client_index, $msg);
    print "\n".date($CONF['date_format'])." Debug - Received unknown input: $input";
    $_client[$this->client_index]['cmd_error_cnt']++;
  }

  /* debug function to see if we can get the server to talk to us */
  private function input_st(){
    global $_client;
    global $CONF;

    print "\n".date($CONF['date_format'])." Received request say something.\r\n";
    $msg = date($CONF['date_format'])." Say something....\r\n";
    socket_write($_client[$this->client_index]['sock'], $msg, strlen($msg));
  }

  /**
   * display "usage" info
   */
  private function input_help(){
    global $CONF;
    global $_client;

    $msg = "\r\n\r\n$CONF[server_name] v:$CONF[server_ver]";
    $this->socket->write($this->client_index, $msg);

    $msg = "DAEMON COMMANDS";
    $this->socket->write($this->client_index, $msg);

    $msg = "  HELP \t lists this info";
    $this->socket->write($this->client_index, $msg);

    $msg = "  EXIT \t disconnect this connection";
    $this->socket->write($this->client_index, $msg);

    $msg = "  SHUTDOWN \t shutdown PIA daemon";
    $this->socket->write($this->client_index, $msg);

    $msg = "  LIST \t lists availabe VPN connections";
    $this->socket->write($this->client_index, $msg);

    $msg = "  CONNECT \"VPN name\" \t establishes a VPN connection";
    $this->socket->write($this->client_index, $msg);

    $msg = "  DISCONNECT \t terminate current VPN connection";
    $this->socket->write($this->client_index, $msg);

    $msg = "  STATUS \t show status information and network configuration";
    $this->socket->write($this->client_index, $msg);

    $msg = "  FORWARD \"IP\"  \t setup port forwarding to specified IP";
    $this->socket->write($this->client_index, $msg);

    $msg = "  AUTH \"username\" \"password\" \t authenticate yourself with username and password";
    $this->socket->write($this->client_index, $msg);

    //update client timeout values
    if( isset($_client[$this->client_index]['token']) == true ){
      $_SESSION[$_client[$this->client_index]['token']]['time_last_cmd_rec'] = microtime(true);
    }
    $_client[$this->client_index]['time_con'] = microtime(true);
  }

  private function shutdown(){
    global $CONF;

    print "\n".date($CONF['date_format'])." good by cruel world...";
    exec('/pia/pia-stop');
    exec('/pia/pia-forward fix quite'); //close stuck sockets ... think I need to close all clients first
    socket_close($this->socket->socket);
    exit;
  }


}

?>