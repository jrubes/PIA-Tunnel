<?php
/*
 * does what javascript tells it vie POST
 */
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */
/* @var $_auth AuthenticateUser */
/* @var $_token token */
if( !$_POST['cmd'] ){ die('invalid'); }
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


switch($_POST['cmd'])
{
  case 'ping':
    $host = escapeshellarg($_POST['IP']);
    $intf = escapeshellarg($_POST['IF']);
    echo "pinged $host over $intf\n";
    exec('/pia/include/ping.sh '.$host.' '.$intf.' &>1 &');
    break;

  case 'read':
    $c = $_files->readfile('/pia/cache/tools_ping.txt');
    echo $c;
    break;
}


?>