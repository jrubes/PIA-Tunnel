#!/usr/bin/php
<?PHP
/*
 * this will be a background daemon accepting commands from a client script using a telnet
 * connection
 *
 * daemon will loop and check work array containing the different things the daemon is supposed to do
 * this is where user commands will be stored
 *
 * the daemon will keep track of what it is currently doing in a state array. newest entry is always
 * [0] counting up to [n]
 *
 * the daemon will execute commands directly or start support scripts
 */

if( !function_exists('socket_create') ) die('Please enable socket support in php.ini. Uncomment extension=sockets.so');


date_default_timezone_set('Europe/Berlin');
set_time_limit(0);
ini_set('memory_limit', '5M'); //pull number out of behind - check how much is used once it works


//#Sessions - start
//Most setups will only allow the apache user to write into the global session directory.
// So this script will use it's own session store
ini_set("session.gc_probability", "0"); // This shoudl disable session garbadge collection
ini_set("session.gc_divisor", "100");   // probability is calculated by using gc_probability/gc_divisor
$session_dir = './PHP_sessions/'; //Defines location of custom dir to store session files
if( is_dir($session_dir) === false )
{
    mkdir($session_dir, '500');
}else{
    if( is_writable($session_dir) != true ){
        die("Unable to write to $session_dir. Please check permissions and try again.\r\n");
    }
}
session_save_path($session_dir);
if( !session_id()) session_start();
//#Sessions - end



$inc_dir = './';
require_once $inc_dir.'class_socket.php';
require_once $inc_dir.'class_pia_daemon.php';

/* configuration */
$CONF['server_ip'] = '127.0.0.1';
$CONF['server_port'] = '6666';
$CONF['server_ver'] = '0.0.1';
$CONF['admin_pw'] = 'admin';
$CONF['server_name'] = 'PIA Tunnel Daemon';
$CONF['server_welcome'] = $CONF['server_name'].' is ready.';
$CONF['timeout_client'] = 20; //Time the client has to respond in seconds
$CONF['timeout_unauth'] = 10;   //n sec to AUTH if not KICK
$CONF['show_server_msg'] = false;
$CONF['show_server_msg_welcome_ack'] = true;
$CONF['max_clients'] = 10; //Allow this many simulatious connections
$CONF['backlog'] = 20; //A maximum of backlog incoming connections will be queued for processing
$CONF['token_length'] = 20; //Length of string to generate which will be MD5'd  md5(microtime(true).token);
$CONF['date_format'] = 'H:i:s'; //PHP date() format
$CONF['inc_dir'] = $inc_dir;
$CONF['server_date_start2'] = date('dMY-'.$CONF['date_format']); //used to calculate uptime 2000000
$CONF['server_date_start'] = microtime(true);

$CONF['max_clients_error_cnt'] = 10;
$CONF['max_clients_reached'] = 0;
$CONF['show_debug'] = false;
$CONF['debug_server_response'] = false; //Warning! setting this to true will break most clients.
                                        //Use telnet to debug manually when this option is true!!!!


$_client = array();  // This multidimensional array will contain client socket information. [n] = client id in array
                    //  $_client[n]['sock'] = socket resource of client
                    //  $_client[n]['token'] = 32 char long string (md5) used to match the
                    //  $_client[n]['time_con'] = date($date_format) of connection time. NOTE, this can be before AUTH
                    //  information in $_SESSIONS['token']['some_setting']


$_SESSION = array(); //Multidimensional array to hold player related information including game stats
    //$_SESSION['token']['some_option']   'token' links to $_client[n]['token']
    //['match_token']          //This ID will link two bots to one match. Both bots will have the same ID here
    //['name']              //Bot name
    //['points_total']     //Total points for this match
    //['points_round']     //Points for this round
    //['match_rounds']     //Rounds this bot has played in the match
    //['game_status']      //Is this bot playing or waiting? Values P or W
    //['last_cmd']         //Keep track of the client's last command so we know what to expect next
    //['cmd_error_cnt']    //Count of communication errors with bot
    //['throws']           //How many dice rolls did the player execute
    //['save']             //How many times did the player save?
    //['time_con']          //Time when the bot first connected, can be used to clear out forgotten bots
    //['time_last_cmd_sent'] //Time of last command sent by the server in microtime
    //['time_last_cmd_rec'] //Time of last command received by the server in microtime
    //['last_cmd_sent']     //Last command we sent to the client
    //['last_cmd_received'] //Last command received from client


$_socket = new Socket($CONF['backlog']); //setup and start the server
$_daemon = new PiaDaemon();
$_socket->create($CONF['server_ip'],$CONF['server_port']);
$_daemon->pass_socket($_socket);

print "\r\n\r\n".$CONF['server_name']." v:$CONF[server_ver] is starting up at ".date($CONF['date_format'])."\r\n";
print "The server will accept connections on $CONF[server_ip]:$CONF[server_port]\r\n";
print "max clients:$CONF[max_clients] client timeout:$CONF[timeout_client] show server msg: ".(($CONF['show_server_msg']===true)?"yes":"no")."\r\n";
print "Welcome msg: $CONF[server_welcome]\r\n";
print "------------------------------------ \r\n\r\n";


/* main loop starts here */
while (true)
{

  //get connected clients
  $read[0] = $_socket->socket;
  for ($i = 0; $i < $CONF['max_clients']; $i++)
  {
      if ( isset($_client[$i]['sock']))
      {
          if( $_client[$i]['sock'] != null) {  //Good socket, store some stuff in the array
              $read[$i + 1] = $_client[$i]['sock'];
          }
      }
  }

      // Set up a non-blocking/blocking call to socket_select()
    $e = null;
    //$g = 1; //Set socket to 1 second timeout, this is non-blocking. Set to $e for blocking socket
    //$f = 0;//Careful, the server will not be able to handle timeouts when the socket is blocking and the server only has two players
    $g=0;$f=400; //Set to non-blocking with 400ms timeout
    $ready = $_socket->select($read,$e,$e,$g,$f);
    /* if a new connection is being made add it to the client array */
    if (in_array($_socket->socket, $read)) {
        for ($i = 0; $i < $CONF['max_clients']; $i++)
        {
            if (!isset($_client[$i]['sock'])) {  //Client does not have a socket yet
                if( $CONF['show_server_msg_welcome_ack'] == true ) print date($CONF['date_format'])." New client connected\r\n";
                $_client[$i]['sock'] = $_socket->accept();
                $msg = "HELO $CONF[server_welcome]";
                $_socket->write($i, $msg);
                $_client[$i]['cmd_error_cnt'] = 0; //Setup error counter
                $_client[$i]['time_con'] = microtime(true);  //Update the field or create new
                break;
            }
            elseif ($i == $CONF['max_clients'] - 1)
                $CONF['max_clients_reached']++;
        }
        if (--$ready <= 0)
            continue;
    } // end if in_array



    /* process server commands here */
    for ($i = 0; $i < $CONF['max_clients']; $i++) // for each client
    {

        if( isset($_client[$i]['sock']))
        {
            if (in_array($_client[$i]['sock'] , $read))
            {
                if( !isset( $_client[$i]['sock'])) //Cleanup disconnected clients
                    unset($_client[$i]);
                if( get_resource_type($_client[$i]['sock']) == 'ResUnknown' ) //Cleanup disconnected clients
                    unset($_client[$i]);

                //Read the socket
                if( isset( $_client[$i]['sock']) ){
                    $input = $_socket->read2($i);
                    //$input = $_socket->read($_client[$i]['sock']);
                }else
                    $input = false;
                if( $input === false ){
                    echo "client removed";
                    unset($_client[$i]);
                }

                //print "Received input: $input\r\n";
                //print "Input as char() ".ord($input);
                if( $input != '' )
                {
                  $a_inp = explode(" ", trim($input));
                  if( $_daemon->pass_client_index($i) === true ) //$i is the index in _client array
                  {
                    $_daemon->switch_input($a_inp[0]);
                  }
                }
            }
        }

    }

}
socket_close($_socket->socket);

function client_cleanup()
{
    //Cleanup disconnected clients fom $_client array
    global $_client;

    foreach( $_client as $key => $dat)
    {
        if( !isset($_client[$key]['sock']) ) unset($_client[$key]);
        if( get_resource_type($_client[$key]['sock']) != 'Socket' ) unset($_client[$key]);
    }
}

function gen_token()
{
    //This function uses rand_alphanumeric() to generate tokens and ensures that the token has not been used during this server run
    global $CONF;

    $str = microtime(true).rand_alphanumeric($CONF['token_length']);
    $str = md5($str);

    return $str;
}

function rand_alphanumeric($length) {
    // return random alphanumeric string
    //based on http://mediumexposure.com/designing-simple-token-algorithm-php/
    $ret_len = 0;
    $ret = '';
    while( $ret_len < $length )
    {
        $subsets[0] = array('min' => 48, 'max' => 57); // ascii digits
        $subsets[1] = array('min' => 65, 'max' => 90); // ascii lowercase English letters
        $subsets[2] = array('min' => 97, 'max' => 122); // ascii uppercase English letters

        // random choice between lowercase, uppercase, and digits
        $s = rand(0, 2);
        $ascii_code = rand($subsets[$s]['min'], $subsets[$s]['max']);
        $ret .= chr($ascii_code);
        $ret_len = strlen($ret);
    }
    if( strlen($ret) == $length )
        return $ret;
    else
        return false;
}

function nicetime($date)
{
    //yasmary at gmail dot com
    //http://www.php.net/manual/en/function.time.php#89415
    if(empty($date)) {
        return "No date provided";
    }

    $periods         = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
    $lengths         = array("60","60","24","7","4.35","12","10");

    $now             = time();
    $unix_date         = strtotime($date);

       // check validity of date
    if(empty($unix_date)) {
        return "Bad date";
    }

    // is it future date or past date
    if($now > $unix_date) {
        $difference     = $now - $unix_date;
//        $tense         = "ago";

    } else {
        $difference     = $unix_date - $now;
//        $tense         = "from now";
    }

    for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
        $difference /= $lengths[$j];
    }

    $difference = round($difference);

    if($difference != 1) {
        $periods[$j].= "s";
    }

    return "$difference $periods[$j]"; //{$tense}";
}
?>