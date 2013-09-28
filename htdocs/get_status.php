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
$type = ( isset($_REQUEST['type']) && $_REQUEST['type'] === 'JSON' ) ? 'JSON' : '';

echo VM_get_status($type);

?>