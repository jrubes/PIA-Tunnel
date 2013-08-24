<?php
/**
 * class to interact with /pia/settings.conf
 * get, store, delete settings
 *
 * @author dev
 */
class PIASettings {
  private $_settings_file;
  private $_files;
  private $_settings;

  function __construct(){
    $this->_settings_file = '/pia/settings.conf';
    $this->_settings = '';
  }

  /**
   * pass the global $_files object
   * @param object $_files
   */
  function set_files(&$files){
    $this->_files = $files;
  }


  /**
   * method to store config settings in settings.conf
   * @param string $setting name of config variable
   * @param string $value value of settings
   */
  function save_settings( $setting, $value ){

    $k = escapeshellarg($setting);
    $v = escapeshellarg($value);
    exec("/pia/pia-settings $k $v");
    //$disp_body .= "$k is now $v<br>\n"; //dev stuff

    //clear to force a reload
    unset($_SESSION['settings.conf']);
  }

  /**
   * method to store settings array like NAMESERVERS[]
   * it removes all values matching NAMESERVERS* then adds the new values
   * @param string $array_name name of array without index, just a string
   * @param string $array2store the complete array as a string formated for BASH.
   *                            it will be written "as is" into settings.conf!
   */
  function save_settings_array( $array_name, &$array2store){
    $index = $array_name."[0]"; //is_settings_array works with a full array key like MYVPN[1]
    if( $this->is_settings_array($index) !== true ){
      return false;
    }

    $this->remove_array($array_name);

    $this->append_settings($array2store);

    //clear to force a reload
    unset($_SESSION['settings.conf']);
  }


  /**
   * append strings to the end of settings.conf<br>
   * this method will not replace settings, use save_settings() for that
   * @param string $addthis string to be appended '>>' to settings.conf
   */
  function append_settings( $addthis ){
    if( $addthis == '' ){ return false; }
    $save = escapeshellarg($addthis);
    exec("echo $save >> '$this->_settings_file'");
  }

  /**
   * method to collect an array from $_POST and return it
   * formated to be stored in settings.conf
   * @param string $arrayname name pf the $_POST array
   * @return array,bool FALSE if none found or one result per key
   */
  function get_array_from_post($arrayname){
    $ret = array();
    if( ! is_array($_POST[$arrayname]) ){ return false; }

    foreach( $_POST[$arrayname] as $val ){
      $ret[] = $val;
    }

    if( count($ret) === 0 ){ return false; }
    return $ret;
  }

  /**
   * reformats an array into a string containing a BASH array
   * which can be stored as is in settings.conf
   * @param array $array2format array to be formated
   */
  function format_array( $name, &$array2format ){
    $cnt = 0;
    $ret = '';

    reset($array2format);
    foreach( $array2format as $val ){
      if( $val != '' ){
        $ret .= $name."[$cnt]='$val'\n";
        ++$cnt;
      }
    }

    return $ret;
  }

  /**
   * method to write an array to settings.conf
   * call this after remove_array()
   * @param array $array2store BASH formatted array of settings
   */
  function write_array( &$array2store ){
    die('decided to go anohter route - not done');
    // add the settings back at the bottom of the file
    reset($array2store);
    foreach( $array2store as $key => $val ){
      for( $x = 0 ; $x < count($values) ; ++$x ){ //yes count in a loop - only doing it since this is a single user script -- ohh yeah, sue me!
        exec("echo '".$config_value.'['.$x."]=\"".$values[$x]."\"' >> '$this->_settings_file'");
        ++$upcnt;
      }
    }

  }

/**
 * methhod to remove a settings array from settings.conf
 * @return int number of lines removed
 */
function remove_array($array_name){
  $removed = 0;
  $ret =  array();

  //get line numbers of current settings
  if( strpos($array_name, '[') === false ){
    //only the array name was passed, without []
    $config_value = $array_name;
  }else{
    $config_value = substr($array_name, 0, strpos($array_name, '[') ); //this is the value of $key without [n]. this is used for the array name when writing it back
  }
  exec('grep -n  "'.$config_value.'" '.$this->_settings_file.' | cut -d: -f1', $ret); // $ret[] will contain line number with current settings

  //loop over returned values and remove the lines
  for( $x = count($ret)-1 ; $x >= 0 ; --$x ){ //go backwards or line numbers need to be adjusted
    exec('sed "'.$ret[$x].'d" '.$this->_settings_file.' > '.$this->_settings_file.'.back');
    exec('mv '.$this->_settings_file.'.back '.$this->_settings_file.'');
    ++$removed;
  }

  return $removed;
}


/**
 * method to check if $config_value is part of a settings array == contains [x]
 * so passing 'FOO[99]' returns true while 'FOO' will not
 * @param string $config_value string containing name of config value
 * @return boolean TRUE when string is an array in settings.conf or FALSE if not
 */
function is_settings_array( $config_value ){
  //arrays contain [] so check for both
  $b_open = strpos($config_value, '[');
  $b_close = strpos($config_value, ']');
  $key = (int)substr($config_value, $b_open+1, (strlen($config_value)-$b_close) ); //get only the array key

  if( $b_open != 0 && $b_close != 0 ){
    //no ensure that [ comes before ]
    if( $b_open < $b_close ){
      //assemble different parts back together to check script logic
      //$assembled will have to == $config_value
      $assembled = substr($config_value, 0, $b_open).'['.$key.']';
      if( $assembled !== $config_value ){
        die('FATAL SCRIPT ERROR 45d: bad logic! Please contact support.'.$assembled.' does not match '.$config_value);
      }
      return true;
    }
  }
  return false;
}

  /**
  * method read /pia/login.conf into an array
  * @return array,bool array with ['name'], ['password'] OR FALSE on failure
  */
 function get_settings(){
   //get settings stored in settings.con
   if( array_key_exists('settings.conf', $_SESSION) !== true ){
     $ret = $this->load_settings();
     if( $ret !== false ){
       return $ret;
     }
   }
   return $_SESSION['settings.conf'];
 }


  /**
   * method to get a list of arrays contained in settings.conf
   *  use $settings[$returnFromThisFunction[0]] to get the current value from settings.conf
   * @return array,bool return array of names,FALSE if no arrays have been found
   * array[0] == 'name of setting'
   * array[1] == 'name of setting2'
   */
  function get_array_list(){
    $ret = array();

    if(array_key_exists('settings.conf', $_SESSION) !== true ){
      if( $this->load_settings() === false ){
        echo "FATAL ERROR: Unable to get list of settings!";
        return false;
      }
    }

    foreach( $_SESSION['settings.conf'] as $key => $val ){
      if( $this->is_settings_array($key) === true ){
        $name_only = substr($key, 0, strpos($key, '[') ); //get only the array name, without key, from $set_key string
        //var_dump($name_only);
        if( array_is_value_unique($ret, $name_only) === true ){
          $ret[] = $name_only;
        }
      }
    }

    if( count($ret) == 0 ){ return false; }
    return $ret;
  }

 /**
  * this function loads settings.conf into an array without comments, stores it in session and return it
  * ['SETTING'] == $VALUE
  * @return array,boolean or false on failure
  */
 function load_settings(){
   $ret = array();
   $c = $this->_files->readfile($this->_settings_file);
   if( $c !== false ){
     $c = explode( "\n", eol($c));
     foreach( $c as $line ){
       //ignore a lot of stuff - quick hack for now
       if(substr($line, 0, 1) != '#'
               && trim($line) != ''
               && substr($line, 0, 4) != 'LANG'
               && substr($line, 0, 1) != '#'
               && substr($line, 0, 11) != 'export LANG'
               && substr($line, 0, 4) != 'bold'
               && substr($line, 0, 6) != 'normal'  ){
         $set = explode('=', $line);
         $ret[$set[0]] = trim($set[1], '"\''); //this should now be one setting per key with setting name as key
       }
     }

     if( count($ret) > 0 ){
       $_SESSION['settings.conf'] = $ret;
       return $_SESSION['settings.conf'];
     }
   }else{
     unset($_SESSION['settings.conf']);
     return false;
   }
 }

 /**
 * method to get an entire settings array as used by build_* functions
 * @param string $name=null *optional* name of array
 * @return string/bool string containing HTML formated as <select> or FALSE
 */
function get_settings_array($name){
  $ret = array();

  if(array_key_exists('settings.conf', $_SESSION) !== true ){
    if( $this->load_settings() === false ){
      echo "FATAL ERROR: Unable to get list of settings!";
      return false;
    }
  }


  /* loop over settings strings and find all with $name* */
    $c=0;
  foreach( $_SESSION['settings.conf'] as $key => $val ){
    //check $key with substring - remove [?]
    $len = strpos($key, '['); // length or string upto [
    if(substr($key, 0, $len) === $name ){
      $ret[] = array( $key , $val );
    }
  }

  if( count($ret) == 0 ){ return false; }
  return $ret;
}


}
?>