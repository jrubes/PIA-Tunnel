<?php
/*
 * This class handles socket communication for PHP5.3 servers
 */

/**
 * I wrote this class a few years ago for a software challange where bots had to play a game of dice
 * I wrote this socket class for my own dice test server but it should work for the PIA daemon as well
 * This is pretty much how I write it back then. Need to go over it when I have time and see where it can be optimized
 *
 * @author Mirko Kaiser
 */
class Socket {
    var $socket;
    var $bl;

    function class_socket(&$backlog)
    {
        $this->socket = null;
        $this->bl = $backlog;
    }

    function create($host,$port)
    {
        //Create a socket and store in $this->socket
        $this->socket = socket_create(AF_INET, SOCK_STREAM, 0) or die("Could not create socket\r\n");

        //This solves the problem with a stuck socket when the server crashes.
        if (!socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            echo socket_strerror(socket_last_error($socket));
            exit;
        }

        // bind socket to port
        socket_bind($this->socket, $host, $port) or die("Could not bind to socket\r\n");
        // start listening for connections
        socket_listen($this->socket, $this->bl) or die("Could not set up socket listener\r\n");
    }

    function select(&$var1, &$var2, &$var3, &$var4, &$var5 )
    {
        //http://de.php.net/socket_select
        return @socket_select($var1,$var2,$var3,$var4,$var5);
    }
    function accept()
    {
        //need to check the manual on this one ;)
        return socket_accept($this->socket);
    }
    function writeold(&$_client_socket, $msg, &$len=null)
    {
        //send to client but only if there is something to send
        if( strlen($msg) > 2 )
        {
            $msg = "$msg\n";    //Add if last char ist nor \n
            if( $len == null ) $len = strlen($msg);
            //$msg = mb_convert_encoding($msg, 'ASCII', "UTF-8");
            //print mb_detect_encoding($msg)."\n";
            socket_write($_client_socket, $msg, $len);
            $_SESSION['time_last_cmd_sent'] = microtime(true);
  //Enable the below
  //on WINDOWS!
            //@socket_write($_client_socket, "\r\n", 2);    //Send a new line after writing everything. This appears to
                                                        //fix communication issues. For example telnet on Windows.
                                                        //Some bots had trouble with this as well.
        }
    }
    function write(&$token_or_client_id, $msg, $opp=null)
    {
        global $_client;
        global $CONF;
        $token = null;

        //Send by token or by client id?
        if( is_string($token_or_client_id) ){    //Function received a token
            $socket = &$_SESSION[$token_or_client_id]['sock'];
            $token = &$token_or_client_id;
        }else{  //Function received a client ID
            $socket = &$_client[$token_or_client_id]['sock'];
            if( isset($_client[$token_or_client_id]['token']) )
                $token = $_client[$token_or_client_id]['token'];
        }

        //send to client but only if there is something to send
        if( strlen($msg) > 2 )
        {
            $msg = "$msg\r\n";    //Add if last char ist nor \n
            $len = strlen($msg);
            //$msg = mb_convert_encoding($msg, 'ASCII', "UTF-8");
            //print mb_detect_encoding($msg)."\n";
            socket_write($socket, $msg, $len);
            if( $token != null ) $_SESSION[$token]['time_last_cmd_sent'] = microtime(true);
            if( $opp != null )
            {
                if( isset($_SESSION[$opp]) ) {
                    $_SESSION[$opp]['time_last_cmd_sent'] = microtime(true)+$CONF['timeout_client']*2;  //Ensure that opp has
                                                                    //enough time to respond to win if the other times out
                }
            }
  //Enable the below
  //on WINDOWS!
            //@socket_write($_client_socket, "\r\n", 2);    //Send a new line after writing everything. This appears to
                                                        //fix communication issues. For example telnet on Windows.
                                                        //Some bots had trouble with this as well.
        }
    }
    function read(&$_client_socket)
    {
        //send to client
        $_SESSION['time_last_cmd_rec'] = microtime(true);
        return @socket_read($_client_socket , 256, PHP_NORMAL_READ);
    }
    function read2(&$token_or_client_id)
    {
        global $_client;
        $token = null;

        //Send by token or by client id?
        if( is_string($token_or_client_id) ){    //Function received a token
            $socket = &$_SESSION[$token_or_client_id]['sock'];
            $token = &$token_or_client_id;
        }else{  //Function received a client ID
            $socket = &$_client[$token_or_client_id]['sock'];
            if( isset($_client[$token_or_client_id]['token']) )
                $token = $_client[$token_or_client_id]['token'];
        }

        //send to client
        if( $token != null ) $_SESSION[$token]['time_last_cmd_rec'] = microtime(true);
        return @trim(socket_read($socket , 256, PHP_NORMAL_READ));
    }
    function close(&$token_or_client_id)
    {
        global $_client;
        $token = null;

        //Send by token or by client id?
        if( is_string($token_or_client_id) ){    //Function received a token
            $socket = &$_SESSION[$token_or_client_id]['sock'];
        }else{  //Function received a client ID
            $socket = &$_client[$token_or_client_id]['sock'];
        }

        if(get_resource_type($socket) == 'Socket')
            socket_close($socket);
    }
}
?>