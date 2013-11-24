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
$type = ( isset($_REQUEST['type']) ) ? $_REQUEST['type'] : '';
$value = ( isset($_REQUEST['value']) ) ? $_REQUEST['value'] : '';

header("Content-Type:text/plain");
switch($type){
  case 'JSON':
    echo json_encode(VM_get_status('array'));
    break;
  case 'value':
    $ar = VM_get_status('array');
    //var_dump($ar);
    $value = trim(strtolower($value));
    reset($ar);
    foreach( $ar as $k => $v ){
      if( trim(strtolower($k)) == $value ){
        echo $ar[$k];
        die();
      }
    }
    break;
  default:
    echo VM_get_status();
    break;

}
?>