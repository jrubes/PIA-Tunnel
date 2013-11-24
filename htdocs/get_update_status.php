<?php
/*
 * script to check origin/master for changes. returns integer of outstanding updates
 * this is called by javascript to provide the user feedback if a new update is available.
 */
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */
/* @var $_auth AuthenticateUser */
/* @var $_token token */

require_once './include/classes/class_files/class_files.php';
$_files = new FilesystemOperations();

header("Content-Type:text/plain");

$cache_file = '/pia/cache/webui-update_status.txt';
//read from cache file or get fresh info
if( file_exists($cache_file) === true ){
  $cont = explode('|', $_files->readfile($cache_file));

  //cont(0) is timestamp of creation
  //cont(1) contains the value
  $expires = strtotime('-4 hours'); //time until session expires
  if( trim($cont[0]) < $expires ){
    $git_ret = get_info();
    if( $git_ret !== false ){
      $txt = strtotime('now').'|'.$git_ret;
      $_files->writefile($cache_file, $txt);
      echo $git_ret;
    }

  }else{
    //return info from cache file
    echo trim($cont[1]);
  }

}else{
  $git_ret = get_info();
  $txt = strtotime('now').'|'.$git_ret;
  $_files->writefile($cache_file, $txt);
  echo $git_ret;
}


/* return integer count of how many commits origin is ahead */
function get_info(){
  $ret = array();
  exec('cd /pia ; git fetch origin ; git rev-list HEAD... origin/release_php-gui --count', $ret);
  if( array_key_exists(0, $ret) === true ){
    return $ret[0];
  }else{
    return false;
  }
}
?>