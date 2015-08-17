<?php
/*
 * script used by online updater to execute pia-update and return the results
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
exec("sudo /usr/local/pia/pia-update", $result);
if( array_key_exists('0', $result) === true ){
  foreach( $result as $val ){
    $ret .= "$val\n";
  }

}

$ret = str_replace(' * ', "\n * ", $ret);
$ret = str_replace(' <<', "\n<<", $ret);
echo $ret;
?>