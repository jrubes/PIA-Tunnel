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

// only show this form if the user has logged in
$_auth->authenticate();



//GUI access to pia-setup
header("Content-Type:text/plain");
$ret = '';
$result = array();
exec("sudo /pia/pia-update", $result);
if( array_key_exists('0', $result) === true ){
  foreach( $result as $val ){
    $ret .= "$val\n";
  }

}
echo $ret;
?>