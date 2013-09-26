<?php
/*
 * basic web framework
 */
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */
/* @var $_auth AuthenticateUser */
/* @var $_token token */

$inc_dir = './include/';
require_once $inc_dir.'basic.php';

//force setup wizard if PIA username is set to default
$login_dta = load_login();
if( $login_dta['username'] == 'your PIA account name on this line' ){
  $_REQUEST['page'] = 'setup-wizard';
}
unset($login_dta);

//user account control was added later so keep the following code for the next few releases
$settings = $_settings->get_settings();
if( $settings['WEB_UI_USER'] == "" && $_REQUEST['page'] != 'setup-wizard'){
  $_REQUEST['page'] = 'setup-admin_account';
}elseif($_REQUEST['page'] != 'setup-wizard'){
  //only allow authenticated users past this point
  if(array_key_exists('username', $_POST) !== true ){ $_POST['username'] = ''; }
  if(array_key_exists('password', $_POST) !== true ){ $_POST['password'] = ''; }
  
  //check if this a new login and validate the token if it is
  if( array_key_exists('Login', $_POST) === true ){
    if( $_token->pval($_POST['token']) !== true ){
      die('invalid login token, the script ends here!');
    }
    
  }
  
  $expected = array( 'username' => $settings['WEB_UI_USER'], 'password' => $settings['WEB_UI_PASSWORD']);
  $supplied = array( 'username' => $_POST['username'], 'password' => $_POST['password']);
  $_auth->authenticate( $expected, $supplied );
}


// load body first because I get the title and meta stuff from the article which is loaded in body
require_once $inc_dir.'body.php';

// now the rest
require_once $inc_dir.'head.php';
require_once $inc_dir.'footer.php';


/* deliver the finished page */
echo $disp_header."\n".$disp_body."\n".$disp_footer;
?>