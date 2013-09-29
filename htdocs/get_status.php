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




if( $value == '' ){
  echo $ret;
}else{
  /**
   * allows an external script to ask for a specific value
  */
  $ar = json_decode($ret);
  var_dump($ar);
  $value = strotolower($value);
  foreach( $ar as $k => $v ){
    echo $k;
    if( strtolower($k) == $value ){
      echo "foo" . $k;
      die();
    }
  }
  //not found
  echo $ret;
  die();
}

?>