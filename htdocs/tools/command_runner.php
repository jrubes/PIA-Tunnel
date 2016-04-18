<?php
/*
 * executes certain system commands
 */
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */
/* @var $_auth AuthenticateUser */
/* @var $_token token */
if( $UNLOCKED !== 'byPIA' ){ die('invalid'); }


$meta['javascript'][] = '/js/command_runner.js';
$disp_body .= disp_command_ui();


/**
 * generates the UI in HTML and returns it
 */
function disp_command_ui(){

  $sel = array(
        'id' => 'cmdsel',
        'selected' => $_REQUEST['cmdsel'],
        array( 'transmission', 'install transmission')
      );

  $ret = '';

  $ret .= '<h2>Shell Command Utility</h2>';
  $ret .= '<noscript><strong>The utility requires javascript. You may use the command line instead</strong></noscript>';

  $ret .= 'Command to execute '.build_select($sel);
  $ret .= ' <input id="btn_exec" type="button" href="#" onclick="execute_command();" name="execute" value="Execute" disabled><br>';

  $ret .= '<textarea id="cmd_out" style="width: 625px; height: 20em;">this box will contain the command output';
  $ret .= "</textarea>\n";





  $ret .= '<script type="text/javascript">'
          .'document.getElementById("btn_exec").disabled = false;'
          .'document.getElementById("cmdsel").focus();'
          .'var timr2;
  function execute_command(){
    document.getElementById("btn_exec").disabled = true;
    var timr1=setTimeout(function(){
      document.getElementById("cmd_out").innerHTML = "running .....";
      var _cmdrun = new CmdRunnerObj();
      _cmdrun.cmdexec("cmd_sel");
      },500);

    timr2=setInterval(function(){
      var _cmdrun = new CmdRunnerObj();
       _cmdrun.read();
       },2500);

  }</script>';

  return $ret;
}

?>