<?php
/*
  * Project: class_token
  * File name: class_password.php
  * Description: class to prevent tampering with website  commands passed via $_GET or $_POST
  * URL: http://www.kaisersoft.net/t.php?ctoken
  *
  * Author: Mirko Kaiser, http://www.KaiserSoft.net
  * Copyright (C) 2011 Mirko Kaiser
  * First created in Germany on 17.02.2012
  * License: New BSD License
      Copyright (c) 2011, Mirko Kaiser, http://www.KaiserSoft.net
      All rights reserved.

      Redistribution and use in source and binary forms, with or without
      modification, are permitted provided that the following conditions are met:
        * Redistributions of source code must retain the above copyright
          notice, this list of conditions and the following disclaimer.
        * Redistributions in binary form must reproduce the above copyright
          notice, this list of conditions and the following disclaimer in the
          documentation and/or other materials provided with the distribution.
        * Neither the name of the <organization> nor the
          names of its contributors may be used to endorse or promote products
          derived from this software without specific prior written permission.

      THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
      ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
      WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
      DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
      DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
      (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
      LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
      ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
      (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
      SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
class token {
  var $token_store; //name of the session array for tokens
  var $paction_store; //name of the session array for protected action tokens
                      //these are seperate because using a protected action token will invalidate all others tokens in the paction store.
  var $saltname; //name of session array holding the salt

  public function __construct()
  {
    $this->token_store = 'PIA_token_store';
    $this->paction_store = 'PIA_token_paction_store';
    $this->saltname = 'PIA_token_salt';
    $cleanup = 500; //maximum number of entries until

    //check if token store already exists in $_SESSION
    if( session_id() == null ) session_start();
    if( array_key_exists( $this->token_store, $_SESSION ) )
    {
      //check that the array is within a certain size and remove the oldest parts
      $cnt = count($_SESSION[$this->token_store]);
      if( $cnt > $cleanup )
      {
        //shift 1/4 of the array to make room for more tokens.
        $strip = $cnt * 0.25;
        for( $y = 0 ; $y < $strip ; ++$y ) array_shift($_SESSION[$this->token_store]);
      }elseif( $cnt === 0 ){
        $_SESSION[$this->token_store] = array(); //ensure that type is array
      }
    }else
      $_SESSION[$this->token_store] = array();

    //same check for protected action store
    if( array_key_exists( $this->paction_store, $_SESSION ) )
    {
      //check that the array is within a certain size and remove the oldest parts
      $cnt = count($_SESSION[$this->paction_store]);
      if( $cnt > $cleanup )
      {
        //shift 1/4 of the array to make room for more tokens.
        $strip = $cnt * 0.25;
        for( $y = 0 ; $y < $strip ; ++$y ) array_shift($_SESSION[$this->paction_store]);
      }elseif( $cnt === 0 ){
        $_SESSION[$this->paction_store] = array(); //ensure that type is array
      }
    }else
      $_SESSION[$this->paction_store] = array();


    //check if $this->saltname exists in session
    if( !array_key_exists($this->saltname, $_SESSION) )
            $_SESSION[$this->saltname] = $this->rand_string(4);
    else{
      //salt exists but does it have the proper length?
      if( strlen($_SESSION[$this->saltname]) !== 4 )
        $_SESSION[$this->saltname] = $this->rand_string(4);
    }
  }

  /**
   * can be used to generate an array with $count random tokens
   * or you may pass an array containing strings it will hash those to be validated later
   * @param array $string_array array of strings to be hashed. return array will use the same index
   * @param int $count=10 number of hashes to return
   * @return array with return sha1 hashes starting at [0]
   */
  public function gen( &$string_array = null , $count = 10)
  {
    return $this->gen_token( $string_array, $count);
  }
  /**
   * protected action token storage
   * can be used to generate an array with $count random tokens
   * or you may pass an array containing strings it will hash those to be validated later
   * @param array $string_array array of strings to be hashed. return array will use the same index
   * @return array with return sha1 hashes starting at [0]
   */
  public function pgen( &$string_array = null , $protected = true )
  {
    return $this->gen_token( $string_array, null , $protected);
  }

  /**
   * can be used to generate an array with $count random tokens
   * or you may pass an array containing strings it will hash those to be validated later
   * @param array $string_array array of strings to be hashed. return array will use the same index
   * @param int $count=10 number of hashes to return
   * @return array with return sha1 hashes starting at [0]
   */
  private function gen_token( &$string_array = null , $count = 10, $paction = false )
  {
    $ret = array();
    $save = array();

    if( $string_array === null )
    {
      //a bunch of tokens. I will generate one random string and append a number
      //to the end because generating the string is slow.
      $rand_string = $this->rand_string(8);
	  $x = 0;
	  while( $x < $count ){
		$hash = sha1($rand_string.$x);
		if( $this->val($hash, null, false) === false ){ //only store unique tokens
			$save[] = $hash; //add an int to make them different
			++$x;
		}
      }
      $ret =& $save; //for return
    }else{
      //hash passed strings
      foreach( $string_array as $key => $val )
      {
        $hash = sha1($val.$_SESSION[$this->saltname]);
        //check if the string is already in the the array
        $ok2save = false;
        if( $paction === true )
        {
          if( $this->pval($hash, null, false) === false )
            $ok2save = true;
        }else{
          if( $this->val($hash, null, false) === false )
            $ok2save = true;
        }

        if( $ok2save === true ){
          $save[$key] = $hash; //never save the same hash twice
          $ret[$key] = $hash; //aways return hashes
        }else{
          $ret[$key] = $hash; //aways return hashes
        }
      }
    }

    //add to token store
    if( count($save) > 0 ){
      if( $paction === false )
        $_SESSION[$this->token_store] = array_merge($_SESSION[$this->token_store], $save);
      else
        $_SESSION[$this->paction_store] = array_merge($_SESSION[$this->paction_store], $save);
    }

    return $ret; //aways return all hashes
  }

 /**
   * will search the session array for the $string passed and remove the value from the token store
   * @param string $sha1_hash a hash previously generated withgen()
   * @param string $string a string to hash and compare to the passed sha1_hash
   * @param bool $clear remove the token from the $_SESSION array if it has been used to validate. Thsi creates a one time TAN list in $_SESISON[$this->token_store]
   * @return bool true if the has has been found in $_SESSION and is valid if $sring was passed, false if not
   */
  public function val( &$sha1_hash, $string = null,  $clear = true ){
    return $this->validate_token( $sha1_hash, $string,  $clear, null );
  }
 /**
   * protected action storage
   * will search the session array for the $string passed and remove the value from the token store
   * @param string $sha1_hash a hash previously generated withgen()
   * @param string $string a string to hash and compare to the passed sha1_hash
   * @param bool $clear remove the token from the $_SESSION array if it has been used to validate. Thsi creates a one time TAN list in $_SESISON[$this->token_store]
   * @return bool true if the has has been found in $_SESSION and is valid if $sring was passed, false if not
   */
  public function pval( &$sha1_hash, $string = null, $clear = true ){
    return $this->validate_token( $sha1_hash, $string, $clear, $this->paction_store );
  }

  /**
   * will search the session array for the $string passed and remove the value from the token store
   * @param string $sha1_hash a hash previously generated withgen()
   * @param string $string a string to hash and compare to the passed sha1_hash
   * @param bool $clear remove the token from the $_SESSION array if it has been used to validate. Thsi creates a one time TAN list in $_SESISON[$this->token_store]
   * @return bool true if the has has been found in $_SESSION and is valid if $sring was passed, false if not
   */
  private function validate_token( &$sha1_hash, $string = null,  $clear = true, $store = null )
  {
    //$sha1_hash must be 40 chars long
    if( strlen($sha1_hash) !== 40 || strlen($_SESSION[$this->saltname]) !== 4 ) return false;

    if( $store === null ) $store = $this->token_store;

    //should $string be compared?
    if( $string !== null )
    {
      $shash = sha1($string.$_SESSION[$this->saltname]);
      foreach( $_SESSION[$store] as $key => $val )
      {
        if( $val === $shash && $shash === $sha1_hash )
        {
          if( $clear === true )
            $_SESSION[$store][$key] = null;

          return true;
        }

      }
      return false;
    }else {
      foreach( $_SESSION[$store] as $key => $val )
      {
        if( $val === $sha1_hash )
        {
          if( $clear === true )
            $_SESSION[$store][$key] = null;

          return true;
        }

      }
      return false;
    }
  }

  /**
   * this will reset the token store and salt
   */
  public function reset()
  {
    $_SESSION[$this->token_store] = array();
    $_SESSION[$this->saltname] = $this->rand_string(4);
  }
  /**
   * this will reset the token store and salt
   */
  public function preset()
  {
    $_SESSION[$this->paction_store] = array();
    $_SESSION[$this->saltname] = $this->rand_string(4);
  }

  /**
  * create a random string, uses mt_rand.
  * @example rand_string(20, array('A','Z','a','z',0,9), '`,~,!,@,#,%,^,&,*,(,),_,|,+,=,-');
  * @example rand_string(16, array('A','Z','a','z',0,9), '.,/')
  * @param integer $lenth length of random string
  * @param array $range specify range as array array('A','Z','a','z',0,9) == [A-Za-z0-9]
  * @param string $other comma separated list of characters !,@,#,$,%,^,&,*,(,)
  * @return string random string of requested length
  */
  public function rand_string($lenth, $range=array('A','Z','a','z',0,9), $other='' ) {
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
  }
}

?>