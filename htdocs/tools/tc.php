<?php
/*
 * script to control tc
 */
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */
/* @var $_auth AuthenticateUser */
/* @var $_token token */
if( $UNLOCKED !== 'byPIA' ){ die('invalid'); }
$meta['javascript'][] = '/js/tc.js';
$disp_body .= disp_tc_ui();








/**
 * generates the ping UI in HTML and returns it
 */
function disp_tc_ui(){
  $ret = '';

  if( $_REQUEST['ping_if'] !== 'tun0' || $_REQUEST['ping_if'] !== 'eth0' || $_REQUEST['ping_if'] !== 'eth1' ){
    $_REQUEST['ping_if'] = 'eth0';
  }

  //interface dropdown
  $sel = array(
        'id' => 'tc_if',
        'selected' => $_REQUEST['ping_if'],
        array( 'eth0', 'eth0'),
        array( 'eth1', 'eth1'),
        array( 'tun0', 'tun0')
      );
  $speed_sel = array(
        'id' => 'speedsel',
        array( '16kbit', '16kbit'),
        array( '32kbit', '32kbit'),
        array( '64kbit', '64kbit'),
        array( '128kbit', '128kbit'),
        array( '256kbit', '256kbit'),
        array( '512kbit', '512kbit'),
        array( '1024kbit', '1024kbit'),
        array( '1536kbit', '1536kbit'),
        array( '2048kbit', '2048kbit'),
        array( '3072kbit', '3072kbit'),
        array( '4096kbit', '4096kbit')
      );


  $ret .= '<h2>Traffic Control Utility</h2>';
  $ret .= '<noscript><strong>The utility requires javascript. You may use the command line instead</strong></noscript>';
  $ret .= '<p>The tc ....'
          .'</p>';
  $ret .= ' <input id="btn_add" type="button" href="#" onclick="send_ping();" name="ping it" value="Add Rules" disabled>';
  $ret .= '<div id="tcfeedback"></div>';
  $ret .= '<textarea id="tcrule" style="width: 625px; height: 20em;">';
  $ret .= "tc qdisc del root dev tun0\n";
  $ret .= "\n";
  $ret .= "# define IF\n";
  $ret .= "tc qdisc add dev tun0 root handle 1: cbq avpkt 1000 bandwidth 1mbit\n";
  $ret .= "\n";
  $ret .= "# limit to 70kBit\n";
  $ret .= "tc class add dev tun0 parent 1: classid 1:1 cbq rate 560kbit allot 1500 prio 5 bounded isolated\n";
  $ret .= "\n";
  $ret .= "# create rule\n";
  $ret .= "tc filter add dev tun0 parent 1: protocol ip prio 16 u32 match ip dst 0.0.0.0/0 flowid 1:1\n";
  $ret .= "</textarea>\n";





$ret .= '<script type="text/javascript">'
          .'document.getElementById("btn_add").disabled = false;'
          .'document.getElementById("dst").focus();'
          .'var timr2
  function send_ping(){
    document.getElementById("btn_add").disabled = true;
    var timr1=setTimeout(function(){
      var _tc = new TCObj();
      _tc.addrule();
      },500);

  }</script>';

  return $ret;
}
?>