<?php
/**
 * class to interact with the various pia-* commands
 *
 * @author MirkoKaiser
 */
class PIACommands {
  private $_files;
  private $_settings;


  /**
   * pass the global $settings object
   * @param object $settings
   */
  function set_settings(&$settings){
    $this->_settings = $settings;
  }


  /**
   * pass the global $_files object
   * @param object $_files
   */
  function set_files(&$files){
    $this->_files = $files;
  }


  /**
   * returns the current status of pia-daemon started by this webUI
   * @return string set to
   * <ul>
   * <li>'running'</li>
   * <li>'offline'</li>
   * </ul>
   */
  function status_pia_daemon(){

    //check the return from screen -ls
    $ex = array();
    exec('ps aux | grep -c "pia-daemon"', $ex);
    if( array_key_exists('0', $ex) === true && (int)$ex[0] > 2 ){ // 2 for command and grep itself
      return 'running';
    }else{
      return 'offline';
    }
  }


  /**
   * method will run a check if tun0 return an IP and assume "UP" when one is found
   * @return booolean TRUE if VPN is up or FALSE if VPN is down
   */
  function is_vpn_up(){
    $ret = array();
     exec('/sbin/ip addr show tun0 2>/dev/null', $ret);
     if( array_key_exists( '0', $ret) !== true ){
       return false;
     }

     return true;
  }


  /**
   * start /pia/pia-start with passed argument
   * @param string $ovpn name of connection or 'daemon' to use MYVPN array
   */
  function pia_connect( $ovpn ){
    $arg = escapeshellarg($ovpn);

    //delete old session.log
    $f = '/pia/cache/session.log';
    $this->_files->rm($f);

    //add header to new session.log
    if( $ovpn === 'daemon' ){
      $set = $this->_settings->get_settings();
      $s = $set['MYVPN[0]'];
      $c = "connecting to $s\n\n";
      $_SESSION['connecting2'] = $s; //store for messages
    }else{
      $c = "connecting to $arg\n\n";
      $_SESSION['connecting2'] = $arg; //store for messages
    }
    $this->_files->writefile( $f, $c ); //write file so status overview works right away

    //time to initiate the connection
    //using bash allows this to happen in the background
    // EDIT: this open the door for the UI to run any command as root. need to remove bash calls!!
    exec("sudo bash -c \"/pia/pia-start $arg &> /pia/cache/php_pia-start.log &\" &>/dev/null &");
  }


  /**
   * interact with /pia/pia-daemon commnad
   * @param string $command string containing command
   * <ul>
   * <li>stop</li>
   * <li>start</li>
   * </ul>
   */
  function pia_daemon( $command ){
    switch( $command ){
      case 'stop':
        $foo = array();
        exec('sudo /pia/pia-daemon stop', $foo);
        break;
      case 'start':
        exec('killall /pia/pia-daemon &> /dev/null');
        exec('sudo bash -c "/pia/pia-daemon &>/pia/cache/pia-daemon.log &" &>/dev/null &');
        break;
    }
  }

  /**
 * method to update the root password with a custom or random password
 * @param string $new_pw new root password as string or 
 */
function update_root_password( $new_pw = null ){
  if( $new_pw == '' || strlen($new_pw) < 3 ){
    $new_pw = $this->rand_string(50);
  }
  $new_pw = escapeshellarg($new_pw);
  
  $out = array();
  $stat = 99;
  exec("sudo /pia/include/update_root.sh $new_pw", $out, $stat);
  
  $ret = "";
  switch($stat){
    case 0:
      $ret = "<div class=\"feedback\">the root password has been changed to '".htmlspecialchars($new_pw)."'</div>\n";
      break;
    case 1:
      $ret = "<div class=\"feedback\">Unkown Error when changing root password...</div>\n";
      break;
    case 2:
      $ret = "<div class=\"feedback\">invalid root password</div>\n";
      break;
    default:
      $ret = "<div class=\"feedback\">root password default text ???!?!?!</div>\n";
      break;
  }  
  return $ret;
}


/**
 * create a random string, uses mt_rand. This one is faster then my old GetRandomString()
 * rand_string(20, array('A','Z','a','z',0,9), '`,~,!,@,#,%,^,&,*,(,),_,|,+,=,-');
 * rand_string(16, array('A','Z','a','z',0,9), '.,/')
 * @param integer $lenth length of random string
 * @param array $range specify range as array array('A','Z','a','z',0,9) == [A-Za-z0-9]
 * @param string $other comma separated list of characters !,@,#,$,%,^,&,*,(,)
 * @return string random string of requested length
 */
function rand_string($lenth, $range=array('A','Z','a','z',0,9), $other='' ) {
  $cnt = count($range);
  $sel_range = array();
  for( $x=0 ; $x < $cnt ; $x=$x+2 )
	$sel_range = array_merge($sel_range, range($range[$x], $range[$x+1]));
  if( $other !== '' )
	$sel_range = array_merge($sel_range, explode (',', $other));
  $out =''; 
  $cnt = count($sel_range);
  for( $x = 0 ; $x < $lenth ; ++$x )
	$out .= $sel_range[mt_rand(0,$cnt-1)];
  return $out; 
/*
    // test the "randomness", replace mt_rand() with rand() to see why you should use mt_rand()
    header("Content-type: image/png");
    $img = imagecreatetruecolor(500,500);
    $ink = imagecolorallocate($img,255,255,255);
    for($i=0;$i<500;++$i) {
	  for($j=0;$j<500;++$j) {
		imagesetpixel($img, mt_rand(1,500), mt_rand(1,500), $ink);
	  }
    }
    imagepng($img);
    imagedestroy($img);
*/
}


}
?>