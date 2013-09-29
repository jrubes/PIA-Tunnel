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
$value = ( isset($_REQUEST['value']) ) ? $_REQUEST['value'] : '';

$ret = VM_get_status($type);

if( $value == '' ){
  echo $ret;
}else{
  /**
   * allows an external script to ask for a specific value
  */
  $ar = json_decode($ret);
  $value = strotolower($value);
  foreach( $ar as $k => $v ){
    if( strtolower($k) == $v ){
      echo $v;
      die();
    }
  }
  //not found
  echo $ret;
  die();
}

?>