<?php

/*
 * this class is supposed to make object dependency simpler
 */
class loader {

  public static $_login;
  public static $_token;
  public static $_pia_settings;
  public static $_gen;
  public static $_config;
  public static $_files;

  public static function PIASettings(){
    self::$_pia_settings = new PIASettings();


    self::$_pia_settings->set_files(self::$_files);

    return self::$_pia_settings;
  }

  public static function loadFiles(){
    self::$_files = new FilesystemOperations();
    return self::$_files;
  }

  public static function loadToken(){
    self::$_token = New token();

    return self::$_token;
  }

  public static function loadGeneral(){
    self::$_gen = New general();

    return self::$_gen;
  }

  public static function loadLogin(){
    self::$_login = new Login();
    //$login->set_db(self::$_db);

    return self::$_login;
  }

  public static function loadConfig( $tbl_name, $cache_prefix){
    self::$_config = New config( $tbl_name, $cache_prefix);
    //$config->set_db(self::$_db);

    return self::$_config;
  }

}

?>