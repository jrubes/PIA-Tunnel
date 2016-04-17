<?php
/*
 * does what javascript tells it via POST
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


//list of commands this script is allowed to execture
$valid_commands = array(
  'transmission' => '/usr/local/pia/include/transmission-install.sh'
);

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
  case 'exec':
    if( array_key_exists( $_POST['exec'], $valid_commands) !== true ){
      exec('/bin/bash -c "/usr/bin/printf \"invalid command\n\nCMDDONE\" > /usr/local/pia/cache/cmd_runner.txt"');
      die();
    }

    echo "executing command {$_POST['exec']}\n";
    exec("bash -c \"{$settings['CMD_SUDO']} {$valid_commands[$_POST['exec']]} &> /dev/null &\" &>/dev/null &");
    break;

  case 'read':
    $c = $_files->readfile('/usr/local/pia/cache/cmd_runner.txt');
    echo $c;
    break;
}

unset($_SESSION['settings.conf']); //force reload
?>