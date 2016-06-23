<?php
/**
 * class to interact with the various pia-* and system commands
 *
 * @author Mirko Kaiser
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
    $set = $this->_settings->get_settings();

    //check the return from screen -ls
    $ex = array();
    exec('ps aux | '.$set['CMD_GREP'].' -c "pia-daemon"', $ex);
    if( array_key_exists('0', $ex) === true && (int)$ex[0] > 2 ){ // 2 for command and grep itself
      return 'running';
    }else{
      return 'offline';
    }
  }

    /**
   * returns the current status of a service by counting the output of ps
   * @return boolean true if service is running, false if not
   */
  function service_count($service_name){
    $set = $this->_settings->get_settings();

    //check the return from screen -ls
    $ex = array();
    $esc = escapeshellarg($service_name);
    exec('ps aux | '.$set['CMD_GREP'].' -c '.$esc, $ex);
    if( array_key_exists('0', $ex) === true && (int)$ex[0] > 2 ){ // 2 for command and grep itself
      return true;
    }else{
      return false;
    }
  }


  /**
   * checks if iptables has forwarding enabled or not
   * @return boolean TRUE when forwarding is enabled, FALSE if not
   *
   */
  function check_forward_state( $interface = '' ){
    $ret = array();
    $pass = escapeshellarg($interface);
    $set = $this->_settings->get_settings();
    exec( $set['CMD_SUDO'].' /usr/local/pia/include/fw_get_forward_state.sh '.$pass, $ret);
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
   * method to rebuild /usr/local/pia/include/autostart.conf file based on configuration settings
   * @return boolean TRUE on success or FALSE on failure
   */
  function rebuild_autostart(){
    $ret = array();
    $set = $this->_settings->get_settings();
    exec( $set['CMD_SUDO'].' /usr/local/pia/include/autostart_rebuild.sh', $ret);
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
   * checks if the internet is up
   * @return boolean true if Internet is up, false if not
   */
  function is_internet_up(){
    $ret = array();
    exec('/usr/local/pia/include/up_internet.sh', $ret);
    if( array_key_exists( 0, $ret) ){
      if( $ret[0] === 'ERROR' ){
        return false;
      }else{
        return true;
      }
    }
  }

  /**
   * method will run a check if tun0 return an IP and assume "UP" when one is found
   * @return booolean TRUE if VPN is up or FALSE if VPN is down
   */
  function is_vpn_up(){
    $ret = array();
    //exec('/sbin/ip addr show tun0 2>/dev/null', $ret);
    exec('/sbin/ifconfig tun0 2>/dev/null', $ret);
    if( array_key_exists( '0', $ret) !== true ){
      return false;
    }

    return true;
  }


  /**
   * deletes the status file so the next version check will be forced
   */
  function clear_update_status(){
    $cache_file = '/usr/local/pia/cache/webui-update_status.txt';
    if( file_exists($cache_file) ){ unlink($cache_file); }
  }

  /**
   * checks cache file or git for count of how many commits origin/ is ahead
   * @param boolean $force_update=false true will ignore the cache
   * @return string number as string containing commit number or a status string
   */
  function get_update_status($force_update=false){
    static $running = false; //set to true will shell script is running
    $cache_file = '/usr/local/pia/cache/webui-update_status.txt';


    //make sure the Internet is up before going further
    $running = true; //set to true to prevent multiple checks at the same time
    if( $this->is_internet_up() !== true ){
      $running = false;
      return 'Internet down';
    }
    $running = false; //back to false for normal operations


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
      $expires = strtotime('-48 hours'); //time until session expires
      if( trim($cont[0]) < $expires ){
        unlink($cache_file);
        if( $running === false ){
          $running = true;
          $this->get_revlist_count();
        }
        return 'checking ...';

      }else{
        //return info from cache file
        $running = false;
        return (int)trim($cont[1]);
      }

    }else{
      $running = true;
      $this->get_revlist_count();
      return 'checking ...';
    }
  }

  /**
   * runs git fetch origin, then rev-list to get number of commits HEAD is in front
   * @return integer|boolean integer containing the number of commits ahead, MIGHT be 0
   *                         or boolean FALSE on failure
   */
  private function get_revlist_count(){
    global $settings;
    $sh = escapeshellarg($settings['GIT_BRANCH']);
    exec("bash -c \" /usr/local/pia/include/log_fetch.sh $sh &>>/dev/null &\" &>/dev/null &");
  }


  /**
   * checks git log and returns information about last $count commits
   * @param integer $count=3 number of log entries to return
   * @return string|boolean string containing output or FALSE on failure
   */
  function git_log( $count=3 ){
    global $settings;
    $ret = array();
    $sret = '';
    $count = escapeshellarg($count);
    exec('cd /usr/local/pia ; '.$settings['CMD_GIT'].' --no-pager log -n '.$count.' --pretty="format:%ci%n>> %s <<%n" origin/'.$settings['GIT_BRANCH'], $ret);

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
   * start /usr/local/pia/pia-start with passed argument
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
      $c = "connecting to $ovpn\n\n";
      $_SESSION['connecting2'] = $ovpn; //store for messages
    }

    $f = '/usr/local/pia/cache/php_pia-start.log';
    $this->_files->rm($f);
    $this->_files->writefile( $f, $c ); //write file so status overview works right away

    //time to initiate the connection
    //using bash allows this to happen in the background
    exec("bash -c \" {$set['CMD_SUDO']} /usr/local/pia/pia-start $arg &>> $f &\" &>/dev/null &");
  }

  /**
   * executes pia-stop
   */
  function pia_disconnect(){
    $this->clear_session();
    $set = $this->_settings->get_settings();

    $this->_files->rm('/usr/local/pia/cache/php_pia-start.log');
    exec("bash -c \" {$set['CMD_SUDO']} /usr/local/pia/pia-stop &>/dev/null &\" &>/dev/null &"); //using bash allows this to happen in the background
  }

  /**
   * interact with /usr/local/pia/pia-daemon commnad
   * @param string $command string containing command
   * <ul>
   * <li>stop</li>
   * <li>start</li>
   * </ul>
   */
  function pia_daemon( $command ){
    $set = $this->_settings->get_settings();
    switch( $command ){
      case 'stop':
        $foo = array();
        exec( $set['CMD_SUDO'].' /usr/local/pia/pia-daemon stop', $foo);
        break;
      case 'start':
        exec('killall /usr/local/pia/pia-daemon &> /dev/null');
        exec( $set['CMD_SUDO'].' /usr/local/pia/pia-daemon stop');
        exec('bash -c "'.$set['CMD_SUDO'].' /usr/local/pia/pia-daemon &>/usr/local/pia/cache/pia-daemon.log &" &>/dev/null &');
        break;
    }
  }

  /**
   * method to clear any cached data
   */
  function clear_session(){
    $f = '/usr/local/pia/cache/session.log';
    $this->_files->rm($f);
    $f = '/usr/local/pia/cache/webui-port.txt';
    $this->_files->rm($f);

    if( array_key_exists('PIA_port', $_SESSION) === true ){
      unset($_SESSION['PIA_port']);
    }

    if( array_key_exists('PIA_port_timestamp', $_SESSION) === true ){
      unset($_SESSION['PIA_port_timestamp']);
    }

    if( array_key_exists('PIA_port_timeout', $_SESSION) === true ){
      unset($_SESSION['PIA_port_timeout']);
    }

    $_SESSION['connecting2'] = '';
    $_SESSION['conn_auth_perma_error'] = false;
    $_SESSION['conn_auth_fail_cnt'] = 0;
    unset($_SESSION['client_id']);
    if( isset($_SESSION['ovpn_providers']) ){ unset($_SESSION['ovpn_providers']); }
    if( isset($_SESSION['ovpn_assembled']) ){ unset($_SESSION['ovpn_assembled']); }
    if( isset($_SESSION['settings.conf']) ){ unset($_SESSION['settings.conf']); }
  }
  /**
 * method to update the root password with a custom or random password
 * @param string $new_pw new root password as string or
 */
function update_root_password( $new_pw = null ){
  $set = $this->_settings->get_settings();
  if( $new_pw == '' || strlen($new_pw) < 3 ){
    $new_pw = $this->rand_string(50);
  }
  $new_pw = escapeshellarg($new_pw);

  $out = array();
  $stat = 99;
  exec( $set['CMD_SUDO']." /usr/local/pia/include/update_root.sh $new_pw", $out, $stat);

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

/**
 * checks if "$mount_point" is mounted
 * @return boolean true or false
 */
function is_mounted( $mount_point ){
  if( $mount_point == '' ){ return false; }
  $set = $this->_settings->get_settings();

  $ret = array();
  $mp = escapeshellarg($mount_point);
  exec('mount | '.$set['CMD_GREP'].' '.$mp ,$ret);

  if( count($ret) > 0 ){
    return true;
  }else{
    return false;
  }
}


/**
 * mount the drive defined in settings
 */
function cifs_mount(){
  $set = $this->_settings->get_settings();
  exec( $set['CMD_SUDO'].' /usr/local/pia/include/cifs_mount.sh');
}

/**
 * unmount the drive defined in settings
 */
function cifs_umount(){
  $set = $this->_settings->get_settings();
  exec( $set['CMD_SUDO'].' /usr/local/pia/include/cifs_umount.sh');
}


/**
 * killall transmission-daemon
 */
function transmission_stop(){
  $set = $this->_settings->get_settings();
  exec( $set['CMD_SUDO'].' /usr/local/pia/include/transmission-stop.sh');
}

/**
 * start transmission-daemon
 */
function transmission_start(){
  $set = $this->_settings->get_settings();
  exec( $set['CMD_SUDO'].' /usr/local/pia/include/transmission-start.sh');
}


}
?>
