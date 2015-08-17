<?php
/*
 * class to handle simple authentication without account mangement
 * the username and password are store elsewehere and are only passed to this
 * class for verification and to setup the session.
 * 
 * the class will compare a username and password passed and return true or false
 *  it will then store an authentication cookie on the client system to rember the user
 * 
 * this is useful to protect an admin interface
 * @author Mirko Kaiser
 */

class AuthenticateUser {
  private $namespace; //this separates the $_SESSION['foo] from other scripts by prefixing the $namespace
                      // so $_SESSION['foo'] becomes $_SESSION[$namespace.'foo']
  private $login_form; //hold the location of the login form
  private $cookie_name;//string, holds name of "login"remember me" cookie
  private $cookie_hash;//value stored on client used to reauthenticate
  private $cookie_lifetime; //time in days for the cookie to life
  
  function __construct(){
    $this->namespace = '45BauNuMYV';
    $this->cookie_name = 'pia-tunnel_reauth';
    $this->cookie_hash = $this->rand_string(10); //this sets a default value but breaks the functionality
                                                //use set_cookie_hash() to pass your value
    $this->login_form = '/usr/local/www/apache24/data/login.php';
    $this->cookie_lifetime = 30;
  }
  
  /**
   * method to set the class session namespace
   * @param string $ns some string to use as for the namespace (strlen > 7)
   */
  function set_namespace( $ns ){
    if( strlen($ns) < 8 ){
      $ns = $this->rand_string(8);
    }
    $this->namespace = $ns;  
  }
  
  /**
   * method to pass a know hash to be used by the "remember me" cookie
   * @param string $hash hash to be used for the re-auth cookie
   */
  function set_cookie_hash( $hash ){
    if( strlen($hash) < 8){
      $hash = $this->rand_string(20);
    }
    $this->cookie_hash = $hash;
  }
  
  function set_cookie_lifetime( $days ){
        if( strlen($days) < 1){
      $days = 0;
    }
    $this->cookie_lifetime = $days;
  }
  
  /**
   * main authentication method, first auth must be username+password then a cookie will be used
   * @param array $expected expected values for ['username],['password']
   * @param array $supplied user supplied values for ['username],['password']
   * @return none will die and display a login form on authentication error
   */
  function authenticate( $expected=array('username' => '', 'password' => ''), $supplied=array('username' => '', 'password' => '')){
    
    if( isset($_SESSION[$this->namespace.'authenticated']) === false ){
      $_SESSION[$this->namespace.'authenticated'] = false;
    }else{
      if( $_SESSION[$this->namespace.'authenticated'] === true ){
        //PHP session still exists so use it
        return true;
      }
    }
    
    //try a password login first
    if( $supplied['username'] !== '' && $supplied['password'] !== '' )
    {
      //looks like an initial login, check that expected is populated
      if( $expected['username'] == '' && $expected['password'] == '' ){
        echo 'Debug: invalid method call, $expected must not be empty!';
        require $this->login_form;
        die();
      }
      
      if( $expected['username'] == $supplied['username'] 
              && $expected['password'] == $supplied['password'] ){
        
        //good login
        $this->login_set_cookie();
        $_SESSION[$this->namespace.'authenticated'] = true;
        return true;
        
      }
    }//end of pw login
    
    
    //check cookie
    if( $this->login_cookie() === true ){
      $_SESSION[$this->namespace.'authenticated'] = true;
      return true;
    }else{
      $_SESSION[$this->namespace.'authenticated'] = false;
      require $this->login_form;
      die();
    }
  
  //catch all
  require $this->login_form;
  die();
  }

  
/** 
 * descroy a user cookie and session
 */  
function logout(){
  //clear "rember me"
  setcookie($this->cookie_name, '', 1); //Clear Old Cookie First
  $this->session_destory();
}  
 
/**
 * completely destroy the user session
 */
private function session_destory()
{
  // delete the session and the stored data
  if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"] );
  }
  $_SESSION = array();
  session_destroy();
}
  
/**
 * method to allow persisten authentication via a cookie
 * @return boolean true if successful or false if not
 */  
private function login_cookie(){
  $cookie_string = '';
  
  if( isset($_COOKIE[$this->cookie_name]) === true ){
    $cookie_string = $_COOKIE[$this->cookie_name];
    if( $cookie_string === $this->cookie_hash ){
      return true;
    }else{
      return false;
    }
  }else{
    return false;
  }
}  
  
private function login_set_cookie( )
{
  if( $this->cookie_lifetime > 0 ){
    setcookie($this->cookie_name, '', 1); //Clear Old Cookie First
    $exp_time = time()+(60*60*24*$this->cookie_lifetime) ; //default to expire after X days
    $path = '/'; //works for entire domain

    $ret = setcookie($this->cookie_name, $this->cookie_hash , $exp_time , $path, null, false, true );
    if( $ret !== true ){
        echo 'Debug: unable to set "remember me" cookie...';
        return false;
    }
  }
  return true;
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
