<?php
/*
 * ping script - requires authentication from the webUI to prevent third party scripts
 * from using this tool without authenticating first.
 */
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */
/* @var $_auth AuthenticateUser */
/* @var $_token token */

$inc_dir = '../include/';
require_once $inc_dir.'basic.php';


/*
 * translates the login form into values used by authentication function
 */
$expected = array( 'username' => $settings['WEB_UI_USER'], 'password' => $settings['WEB_UI_PASSWORD']);
$supplied = (isset($_POST['username']) && isset($_POST['username']) )
                ? array( 'username' => $_POST['username'], 'password' => $_POST['password'])
                : array( 'username' => '', 'password' => '');
$_auth->authenticate( $expected, $supplied );


/* common header for output */
$disp_head = '<!DOCTYPE html>'
              .'<html>'
              .'<head>'
                .'<meta charset="UTF-8">'
                .'<title>PIA-Tunnel Management Interface</title>'
                .'<meta name="author" content="Mirko Kaiser">'
                .'<meta name="keywords" content="">'
                .'<meta name="description" content="">'
                .'<meta name="robots" content="NOINDEX,NOFOLLOW">'
                .'<meta name="dcterms.creator" content="Mirko Kaiser">'
                .'<script src="../js/RequestHandler.js" type="text/javascript"></script>'."\n"
                .'<script src="../js/ping.js" type="text/javascript"></script>'."\n"
              .'</head>';




if( !isset($_POST['IP']) ){
  $disp_body .= disp_ping_ui();

  echo $disp_head."\n".$disp_body."\n</body></html>";

}else{
  header("Content-Type:text/html");
  disp_ping_output_ui();
  $disp_body .= disp_ping_output_ui();

  echo $disp_head."\n".$disp_body."\n</body></html>";
}













/**
 * generates HTML form used to display output from "ping"
 * @return string
 */
function disp_ping_output_ui(){
  $ret = '';

  $ret .= '<h2>Ping Utility</h2>';
  $ret .= '<noscript><strong>The utility requires javascript. You may use the command line instead</strong></noscript>';
  $ret .= '<p>Running ping -qn -i 0.5 -w 4 -W 0.5 '.$_POST['IP'].' > /pia/cache/tools_ping.txt</p>';
  $ret .= '<textarea id="ping_out" style="width: 80%; height: 20em;">attempting to retrieve output from /pia/cache/tools_ping.txt ....';
  $ret .= "</textarea>\n";
  $ret .= '<input type="hidden" id="ip2ping" value="'.$_POST['IP'].'">';
  $ret .= '<input type="hidden" id="running" value="0">';

  $ret .= '<script type="text/javascript">'
          .'document.getElementById("ping_out").focus();'
          .'var timr1=setInterval(function(){'
                            .'document.getElementById("ping_out").innerHTML = "ping started .....";'
                            .'var _ping = new PingObj();'
                            .'_ping.ping("ip2ping");clearInterval(timr1);'
                            .'},500);'
          .'var timr2=setInterval(function(){'
                            .'var _ping = new PingObj();'
                            .'_ping.read();'
                            .'},4000);</script>';

  return $ret;
}



/**
 * generates the ping UI in HTML and returns it
 */
function disp_ping_ui(){
  $ret = '';

  if( $_REQUEST['ping_if'] !== 'tun0' || $_REQUEST['ping_if'] !== 'eth0' || $_REQUEST['ping_if'] !== 'eth1' ){
    $_REQUEST['ping_if'] = 'eth0';
  }

  //interface dropdown
  $sel = array(
        'id' => 'ping_if',
        'selected' => $_REQUEST['ping_if'],
        array( 'eth0', 'eth0'),
        array( 'eth1', 'eth1'),
        array( 'tun0', 'tun0')
      );


  $ret .= '<h2>Ping Utility</h2>';
  $ret .= '<noscript><strong>The utility requires javascript. You may use the command line instead</strong></noscript>';
  $ret .= '<p>Try a ping by hostname first. This will check domain name lookup and proper connection.<br>'
          . 'Please keep in mind that a lot/most websites block ping requests ....<br>'
          . '</p>';
  $ret .= '<p>The following firewall rules apply<br>'
          .'* outgoing eth0 ping allowed when VPN is disconnected<br>'
          .'* outgoing eth0 not allowed when VPN is connected. use tun0 instead'
          .'</p>';

  $ret .= '<form action="/tools/ping.php" method="post" onsubmit="return false;">';
  $ret .= '<input type="hidden" name="cmd" value="ping_host">';
  $ret .= 'Outgoing interface: '.build_select($sel).'<br>';
  $ret .= 'Name or IP ';
  $ret .= ' <input id="inp_host" type="text" name="IP" placeholder="google.com" value="" style="width: 20em;"> ';
  $ret .= ' <input id="btn_ping" type="button" onclick="send_ping();" name="ping it" value="Ping Host" disabled>';
  $ret .= "</form>\n";

  $ret .= '<textarea id="ping_out" style="width: 600px; height: 20em;">ping results are stored in /pia/cache/tools_ping.txt ....';
  $ret .= "</textarea>\n";





  $ret .= '<script type="text/javascript">'
          .'document.getElementById("btn_ping").disabled = false;'
          .'document.getElementById("inp_host").focus();'
          .'var timr2;
  function send_ping(){
    document.getElementById("btn_ping").disabled = true;
    var timr1=setTimeout(function(){
      document.getElementById("ping_out").innerHTML = "ping started .....";
      var _ping = new PingObj();
      _ping.ping("inp_host");
      },500);

    timr2=setInterval(function(){
      var _ping = new PingObj();
       _ping.read();
       },4000);

  }</script>';

  return $ret;
}
?>