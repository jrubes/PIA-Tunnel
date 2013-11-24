<?php
/*
 * script to allow other scripts to check the current status of the VPN connection
 * returns status info as JSON array
 */
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */
/* @var $_auth AuthenticateUser */
/* @var $_token token */

$inc_dir = './include/';
require_once $inc_dir.'basic.php';

$count = ( array_key_exists('count', $_POST) === true && $_POST['count'] > 0 ) ? $_POST['count'] : 10;

header("Content-Type:text/plain");
echo $_pia->git_log($count);
?>