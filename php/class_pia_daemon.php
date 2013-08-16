<?php

/**
 * Description of class_pia_daemon
 *
 * @author dev
 */
class PiaDaemon {
  var $socket;
  var $client_index;

  /* pass the socket object */
  function pass_socket( &$socket ){
    $this->socket = $socket;
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
    switch( $input )
    {
        case 'ST':    //DEBUG COMMAND
          $this->input_st();
          break;
        case 'HELP':    //List public commands
          $this->input_help();
          break;
        case 'EXIT';
          $this->disconnect_client();
          break;
        default:
          $this->input_invalid($input);
            break;
    }
  }

  private function disconnect_client(){
    global $_client;

    $msg = "Good bye.\r\n".chr(0);
    $this->socket->write($this->client_index, $msg);
    $this->socket->close($this->client_index);
    unset($_client[$this->client_index]); //Remove token since client disconnected
  }

  private function input_invalid( &$input ){
    global $_client;

    $msg = "UNKOWN COMMAND $input";
    $this->socket->write($this->client_index, $msg);
    $msg = "try HELP for a list of commands";
    $this->socket->write($this->client_index, $msg);
    print "Debug - Received unknown input: $input\r\n";
    $_client[$this->client_index]['cmd_error_cnt']++;
  }

  /* debug function to see if we can get the server to talk to us */
  private function input_st(){
    global $_client;
    global $CONF;

    print date($CONF['date_format'])." Received request say something.\r\n";
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




}

?>