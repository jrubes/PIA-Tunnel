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
  private $settings_array_changes;
  private $settings_changed;

  function __construct(){
    $this->_settings_file = '/pia/settings.conf';
    $this->_settings = '';
    $this->settings_array_changes = 0;
    $this->settings_changed = 0;
  }

  /**
   * pass the global $_files object
   * @param object $_files
   */
  function set_files(&$files){
    $this->_files = $files;
  }
  
  
  
  /**
   * returns the number of members an array setting contains
   * @param string $name name of member without []
   */
  function get_array_count( $name ){
      $settings = $this->get_settings();
      $name_len = mb_strlen($name);
      $cnt = 0;
      
      reset($settings);
      foreach( $settings as $key => $val ){
          if( substr($key, 0, $name_len) === $name  ){
              ++$cnt;
          }
      }
      return $cnt;
  }
  

  /**
   * main method to call when you want settings stored
   * @param string $fields_string comma separated string of form fields to be expected in POST
   *                              every field name is checked with $this->is_settings_array so only
   *                              pass the array names without key
   */
  function save_settings_logic( &$fields_string ){
    $ret = '';
    $onechanged=false;
    $post2expect = explode(',', $fields_string);
    if( count($post2expect) == 0 ){
      die('fatal error, count zero in save_settings_logic!');
    }

    //check how each POSTed setting should be handled
    foreach( $post2expect as $posted ){
      if( $this->is_settings_array($posted) === true ){
        /*# this is a settings array, compare post with existing settings #*/
          
        $parray = $this->get_post_array($posted, true);
        $sarray = $this->get_settings_array($posted);
        $tmp_array = $this->compare_settings_arrays( $sarray, $parray);

        /* new_settings now contains the new values to be stored or empty for "no values" */
        $array2store = $this->format_array($posted, $tmp_array);
        $this->save_settings_array($posted, $array2store);
        if( $this->settings_array_changes > 0 ){
          $onechanged=true;
        }else{
          //echo "no changes to $posted<br>";
        }

      }else{
        /*# regular string setting  #*/
        $settings = $this->get_settings();

        //handle regular strings here
        if( array_key_exists($posted, $_POST) === true && $settings[$posted] != $_POST[$posted] ){
          //setting found and setting has changed, UPDATE!
          $this->save_settings($posted, $_POST[$posted]);
          $onechanged=true;
        }else{
          //echo "NOT: s: ".$settings[$posted]." vs p: ".$_POST[$posted]."<br>";
        }
      }
    }

    if( $onechanged === true ){
      $ret = "<div id=\"feedback\" class=\"feedback\">Settings updated</div>\n";
    }
    return $ret;
  }

  /**
   * method to store config settings in settings.conf
   * @param string $setting name of config variable
   * @param string $value value of settings
   */
  function save_settings( $setting, $value ){

    //only store the password if it is not empty
    $setting_part = substr( $setting, -9);
    if( $setting_part === '_PASSWORD' && $value == '' ){
        //write old password to $value to keep the old one
        $value = $_SESSION['settings.conf'][$setting];
    }
  
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

    //the array functions do not honor line numbers so strip all comments as they will not
    //be above the settings anymore
    exec('sed \'/^#/ d\' "/pia/settings.conf" > "/pia/settings.conf.bak"');
    exec('mv "/pia/settings.conf.bak" "/pia/settings.conf"');
  
    // ensure SettingsArray of [0] always exists
    if( $array2store === '' ){
        $array2store = "$index=''";
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
   * compare settings arrays and create a diff array
   * use get_post_array( foo, <b>true</b>) or get_settings_array() to get the proper format
   * @param array $settings_array <b>settings array</b> with following structure
   *  <ul><li>[0][0] = 'array name with key'</li>
   *      <li>[0][1] = 'config value'</li>
   *  </ul>
   * @param array $array2 array with following structure
   *  <ul><li>[0][0] = 'array name or array name with key'</li>
   *      <li>[0][1] = 'config/post value'</li>
   *  </ul>
   * @return array array containing the new settings array
   */
  function compare_settings_arrays( $settings_array, $array2 ){
    $new = array();
    $this->settings_array_changes = 0;

    //do a count comparsion to check for removed values
    if( count($settings_array) != count($array2) ){
      ++$this->settings_array_changes;//count mismatch - something changed
    }

    //compare each setting against array2 and look for matches
    reset($settings_array);
    foreach( $settings_array as $array_key => $array_setting ){

      if( is_array($array2) === true ){
          reset($array2);
          foreach( $array2 as $array2_key => $array2_inside ){
          if( $array2_inside[1] != $array_setting[1] ){
            ++$this->settings_array_changes;
          }
          $new[] = $array2_inside[1];
          unset($array2[$array2_key]); //remove as it has been processed
          unset($settings_array[$array_key]); //remove as it has been processed
          break;
        }
      }else{
        //no values posted so this value should be deleted, unset settings
        unset($settings_array[$array_key]); //remove as it has been processed
      }
    }

    //now check for leftovers in array2, these are new settings so just store them
    if( is_array($array2) === true ){
      reset($array2);
      foreach( $array2 as $array2_key => $array2_inside ){
        ++$this->settings_array_changes;
        $new[] = $array2_inside[1];
        unset($array2[$array2_key]); //remove as it has been processed

      }
    }

    return $new;
  }


  /**
   * method to collect an array from $_POST and return it
   * formated to be stored in settings.conf
   * @param string $arrayname name pf the $_POST array
   * @param bool $$multidimensinal=false returns multidimensional array when true
   * @return array,bool FALSE if none found or one result per key
   */
  function get_post_array($arrayname, $multidimensional=false){
    $ret = array();
    $multi = 0;
    if( !array_key_exists($arrayname, $_POST) ){ return false; }
    if( !is_array($_POST[$arrayname]) ){ return false; }

    foreach( $_POST[$arrayname] as $val ){
      if( $multidimensional === true ){
        $ret[$multi][0] = $arrayname;//only contain the array name without key
        $ret[$multi][1] = $val;
        ++$multi;
      }else{
        $ret[] = $val;
      }
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

    if( count($array2format) === 0 ){
      //no values, create empty array to have a default in settings.conf
      return $name.'[0]=""';
    }

    reset($array2format);
    foreach( $array2format as $val ){
      if( $val != '' ){
        $ret .= $name."[$cnt]='$val'\n";
        ++$cnt;
      }
    }

    $ret = trim($ret, "\n");
    return $ret;
  }


/**
 * method to remove a settings array from settings.conf
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
//    exec('sed -e :a -e \'/^\n*$/{$d;N;};/\n$/ba\' '.$this->_settings_file.'.back');
    exec('mv '.$this->_settings_file.'.back '.$this->_settings_file.'');
    ++$removed;
  }

  return $removed;
}


/**
 * method to check if $config_value is part of a settings array
 * it checks settings.conf for a matching array with [0] as this must always exist, even if empty
 * @param string $array_name string containing name of config value
 * @return boolean TRUE when string is an array in settings.conf or FALSE if not
 */
function is_settings_array( $array_name ){
  $settings = $this->get_settings();

  // array name may be passed as 'foo' or 'foo[99]'
  if( strpos($array_name, '[') === false ){
    $config_value = $array_name;
  }else{
    $config_value = substr($array_name, 0, strpos($array_name, '[') ); //this is the value of $key without [n]. this is used for the array name when writing it back
  }

  //check if $config_value[0] is in settings
  if( array_key_exists($config_value.'[0]', $settings) === true || $config_value === 'MYVPN' ){
    return true;
  }else{
    return false;
  }
}

  /**
  * read /pia/settings.conf into an array
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