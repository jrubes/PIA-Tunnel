<?php
/*
 * script to handle the upgrade to 
 */
/* @var $_settings PIASettings */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */
/* @var $_pia PIACommands */


switch($_REQUEST['cmd']){
  case 'store_admin_info':
    $disp_body .= $_settings->save_settings_logic($_POST['store_fields']);
    break;
  default:
    $settings = $_settings->get_settings();
    $disp_body .= disp_admin_ui_default(); 
    break;
}  

/**
 * function to display a default ui
 */
function disp_admin_ui_default(){
  global $_pia;
  $fields = '';
  
  $disp_body = '';

  $disp_body .= '<form action="/?cmd=store_admin_info" method="post">'."\n";
  $disp_body .= '<p>Invalid Web-UI user info detected. Please setup your account below.</p>';

  $disp_body .= "<table>\n";
  $disp_body .= '<tr><td>Web-UI Username</td><td><input type="text" style="width: 15em" name="WEB_UI_USER" value="" placeholder="Username for the Web-UI"></td>';
  $disp_body .= '<tr><td>Web-UI Password</td><td><input type="password" style="width: 15em" name="WEB_UI_PASSWORD" value="" placeholder="Password for the Web-UI"></td>';
  $disp_body .= "</table>\n";
  $disp_body .= '<input type="hidden" name="WEB_UI_NAMESPACE" value="'.$_pia->rand_string(10).'">';
  $disp_body .= '<input type="hidden" name="WEB_UI_COOKIE_AUTH" value="'.$_pia->rand_string(20).'">';
  $fields .= 'WEB_UI_USER,WEB_UI_PASSWORD,WEB_UI_NAMESPACE,WEB_UI_COOKIE_AUTH';

  $disp_body .= '<input type="hidden" name="store_fields" value="'.$fields.'">';
  $disp_body .= '<br><input type="submit" name="store_admin_info" value="Save Info">';
  $disp_body .= "</form></p>\n";
 
  return $disp_body;
}
?>