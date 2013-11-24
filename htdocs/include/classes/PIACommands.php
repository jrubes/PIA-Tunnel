<?php
/**
 * class to interact with the various pia-* and system commands
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
   * checks if iptables has forwarding enabled or not
   * @return boolean TRUE when forwarding is enabled, FALSE if not
   *
   */
  function check_forward_state(){
    $ret = array();
    exec('sudo /pia/include/fw_get_forward_state.sh', $ret);
    if( array_key_exists( '0', $ret) === true ){
        if( $ret[0] === 'ON' ){
            return true;
        }else{
            return false;
        }
    }else{
        return false;
    }
  }

   /**
   * method to rebuild /pia/include/autostart.conf file based on configuration settings
   * @return boolean TRUE on success or FALSE on failure
   */
  function rebuild_autostart(){
    $ret = array();
    exec('sudo /pia/include/autostart_rebuild.sh', $ret);
    if( array_key_exists( '0', $ret) === true ){
        if( $ret[0] === 'OK' ){
            return true;
        }else{
            return false;
        }
    }else{
        return false;
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
   * checks cache file or git for count of how many commits origin/ is ahead
   * @param boolean $force_update=false true will ignore the cache
   * @return string number as string containing commit number or a status string
   */
  function get_update_status($force_update=false){
    $cache_file = '/pia/cache/webui-update_status.txt';

    if( $force_update === true ){
        $git_ret = $this->get_revlist_count();
        if( $git_ret !== false ){
          $txt = strtotime('now').'|'.$git_ret;
          $this->_files->writefile($cache_file, $txt);
          return (int)$git_ret;
        }else{
          //file does not exist. create dummy file to show page as quickly as possible
          // the next javascript status update will run the actuall check in the background
          $txt = '0|0';
          $this->_files->writefile($cache_file, $txt);
          return 'error checking for updates';
    }
    }

    //read from cache file or get fresh info
    if( file_exists($cache_file) === true ){
      $cont = explode('|', $this->_files->readfile($cache_file));

      //cont(0) is timestamp of creation
      //cont(1) contains the value
      $expires = strtotime('-4 hours'); //time until session expires
      if( trim($cont[0]) < $expires ){
        $git_ret = $this->get_revlist_count();
        if( $git_ret !== false ){
          $txt = strtotime('now').'|'.$git_ret;
          $this->_files->writefile($cache_file, $txt);
          return (int)$git_ret;
        }

      }else{
        //return info from cache file
        return (int)trim($cont[1]);
      }

    }else{
      //file does not exist. create dummy file to show page as quickly as possible
      // the next javascript status update will run the actuall check in the background
      $txt = '0|0';
      $this->_files->writefile($cache_file, $txt);
      return 'checking ...';
    }
  }

  /**
   * runs git fetch origin, then rev-list to get number of commits HEAD is in front
   * @return integer|boolean integer containing the number of commits ahead, MIGHT be 0
   *                         or boolean FALSE on failure
   */
  private function get_revlist_count(){
    $ret = array();
    exec('cd /pia ; git fetch origin &> /dev/null ; git rev-list HEAD... origin/release_php-gui --count 2> /dev/null', $ret);
    if( array_key_exists(0, $ret) === true ){
      return (int)$ret[0];
    }else{
      return false;
    }
  }

  /**
   * checks git log and returns information about last $count commits
   * @param integer $count=3 number of log entries to return
   * @return string|boolean string containing output or FALSE on failure
   */
  function git_log( $count=3 ){
    $ret = array();
    $sret = '';
    $count = escapeshellarg($count);
    exec('cd /pia ; git --no-pager log -n '.$count.' --pretty="format:%ci%n>> %s <<%n"', $ret);

    $cnt = count($ret);
    if( $cnt > 0 ){
      for( $x=0 ; $x < $cnt ; ++$x ){
        $sret .= "{$ret[$x]}\n";
      }

      //add a line breaks to make it look pretty
      $sret = str_replace(' * ', "\n * ", $sret);
      $sret = str_replace(' <<', "\n<<", $sret);
      return trim($sret);
    }else{
      return false;
    }
  }

  /**
   * start /pia/pia-start with passed argument
   * @param string $ovpn name of connection or 'daemon' to use MYVPN array
   */
  function pia_connect( $ovpn ){
    $arg = escapeshellarg($ovpn);

    $this->clear_session();

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

    $f = '/pia/cache/php_pia-start.log';
    $this->_files->rm($f);
    $this->_files->writefile( $f, $c ); //write file so status overview works right away

    //time to initiate the connection
    //using bash allows this to happen in the background
    exec("bash -c \"sudo /pia/pia-start $arg &>> $f &\" &>/dev/null &");
  }

  /**
   * executes pia-stop
   */
  function pia_disconnect(){
    $this->clear_session();

    $this->_files->rm('/pia/cache/php_pia-start.log');
    exec("bash -c \"sudo /pia/pia-stop &>/dev/null &\" &>/dev/null &"); //using bash allows this to happen in the background
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
        exec('sudo /pia/pia-daemon stop');
        exec('bash -c "sudo /pia/pia-daemon &>/pia/cache/pia-daemon.log &" &>/dev/null &');
        break;
    }
  }

  /**
   * method to clear any cached data
   */
  function clear_session(){
    $f = '/pia/cache/session.log';
    $this->_files->rm($f);
    $f = '/pia/cache/webui-port.txt';
    $this->_files->rm($f);

    if( array_key_exists('PIA_port', $_SESSION) === true ){
      unset($_SESSION['PIA_port']);
    }

    if( array_key_exists('PIA_port_timestamp', $_SESSION) === true ){
      unset($_SESSION['PIA_port_timestamp']);
    }

    $_SESSION['connecting2'] = '';
    unset($_SESSION['client_id']);
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
      $ret = "<div id=\"feedback\" class=\"feedback\">root password set to '".htmlspecialchars($new_pw)."'</div>\n";
      break;
    case 1:
      $ret = "<div id=\"feedback\" class=\"feedback\">Unkown Error changing the root password...</div>\n";
      break;
    case 2:
      $ret = "<div id=\"feedback\" class=\"feedback\">invalid root password</div>\n";
      break;
    default:
      $ret = "<div id=\"feedback\" class=\"feedback\">SUPER MAJOR ERROR: root password default text ???!?!?!</div>\n";
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