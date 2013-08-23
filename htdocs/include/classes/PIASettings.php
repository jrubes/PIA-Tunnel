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

  function __construct(){
    $this->_settings_file = '/pia/settings.conf';
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
     $ret = load_settings();
     if( $ret !== false ){
       return $ret;
     }
   }
   return $_SESSION['settings.conf'];
 }
}
?>