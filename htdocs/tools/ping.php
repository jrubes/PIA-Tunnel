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
if( $UNLOCKED !== 'byPIA' ){ die('invalid'); }
$meta['javascript'][] = './js/ping.js';
$disp_body .= disp_ping_ui();




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
  $ret .= '<p>The Ping Utility uses the same ping commands as the PIA-Tunnel scripts.<br>'
          .'Try pining a hostname (google.com) to see if name resolution works or '
          . 'your computers/router by IP if everything fails.....'
          .'</p>';
  $ret .= '<p>The following firewall rule applies<br>'
          .'* outgoing eth0 not allowed when the VPN is connected. connections to the Internet are '
          . ' only allowed through tun0'
          .'</p>';

  $ret .= '<input type="hidden" name="cmd" value="ping_host">';
  $ret .= 'Outgoing interface: '.build_select($sel).'<br>';
  $ret .= 'Name or IP ';
  $ret .= ' <input id="inp_host" type="text" name="IP" placeholder="google.com" value="" style="width: 20em;"> ';
  $ret .= ' <input id="btn_ping" type="button" href="#" onclick="send_ping();" name="ping it" value="Ping Host" disabled>';

  $ret .= '<textarea id="ping_out" style="width: 625px; height: 20em;">ping results are stored in /pia/cache/tools_ping.txt ....';
  $ret .= "</textarea>\n";





  $ret .= '<script type="text/javascript">'
          .'document.getElementById("btn_ping").disabled = false;'
          .'document.getElementById("inp_host").focus();'
          .'var timr2;
  function send_ping(){
    document.getElementById("btn_ping").disabled = true;
    var timr1=setTimeout(function(){
      document.getElementById("ping_out").innerHTML = "running .....";
      var _ping = new PingObj();
      _ping.ping("inp_host");
      },500);

    timr2=setInterval(function(){
      var _ping = new PingObj();
       _ping.read();
       },2500);

  }</script>';

  return $ret;
}
?>