<?php

/*
 * this class is supposed to make object dependency simpler
 */
class loader {

  public static $_jobs;
  public static $_db;
  public static $_login;
  public static $_token;
  public static $_dyn;
  public static $_email;
  public static $_tiny;
  public static $_gen;
  public static $_config;
  public static $_files;

  public static function loadDynTable(){
    self::$_dyn = new dynamische_tabellen();

    //$dyn->set_db(self::$_db);
    self::$_dyn->set_jobs(self::$_jobs);
    self::$_dyn->set_login(self::$_login);
    self::$_dyn->set_token(self::$_token);
    self::$_dyn->set_email(self::$_email);
    self::$_dyn->set_tiny(self::$_tiny);
    self::$_dyn->set_config(self::$_config);
    self::$_dyn->set_files(self::$_files);

    return self::$_dyn;
  }

  public static function loadJobRegistry(){
    self::$_jobs = new jobs();
    //$job->set_db(self::$_db);

    return self::$_jobs;
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

  public static function loadTinyManage(){
    self::$_tiny = new tiny_manage();
    //$tiny->set_db(self::$_db);

    return self::$_tiny;
  }

  public static function loadEmail(){
    self::$_email = new SendEmail();

    return self::$_email;
  }

}

?>