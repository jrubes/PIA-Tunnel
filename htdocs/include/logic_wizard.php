<?php
/*
 * this page wil display a basic setup UI and store the settings when done
 */
/* @var $_settings PIASettings */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */
/* @var $_pia PIACommands */



//act on $CMD variable
switch($_REQUEST['cmd']){
  case 'store_setting':
    $disp_body .= update_user_settings();
    $disp_body .= $_settings->save_settings_logic($_POST['store_fields']);
    $disp_body .= $_pia->update_root_password($_POST['new_root_password']);
    $_settings->save_settings('SETUP_WIZARD_COMPLETED', 'yes');

    $disp_body .= '<div class="box">';
    $disp_body .= '<p>All done! <a href="/?page=main">Please login to continue</a></p>';
    $disp_body .= '</div>';
    break;
  default:
    $disp_body .= disp_wizard_default();
    break;
}


/**
 * generates a setup UI for the user
 * @global PIASettings $_settings
 */
function disp_wizard_default(){
  global $_settings;
  global $_pia;

  $settings = $_settings->get_settings();
  $disp_body = '';
  $fields = ''; //comma separate list of settings offered here

  $disp_body .= '<div class="box wizard">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="dhcpd_settings">';
  $disp_body .= '<h2>PIA-Tunnel Setup Wizard</h2>'."\n";

  //web UI account
  $disp_body .= 'Please enter a username and password for logging into the Web-UI<br>';
  $disp_body .= "<table>\n";
  $disp_body .= '<tr><td>Web-UI Username</td><td><input type="text" style="width: 15em" name="WEB_UI_USER" value="" placeholder="Username for the Web-UI" required></td>';
  $disp_body .= '<tr><td>Web-UI Password</td><td><input type="password" style="width: 15em" name="WEB_UI_PASSWORD" value="" placeholder="Password for the Web-UI" required></td>';
  $disp_body .= "</table>\n";
  $disp_body .= '<input type="hidden" name="WEB_UI_NAMESPACE" value="'.$_pia->rand_string(10).'">';
  $disp_body .= '<input type="hidden" name="WEB_UI_COOKIE" value="'.$_pia->rand_string(20).'">';
  $disp_body .= '<hr>';
  $fields .= 'WEB_UI_USER,WEB_UI_PASSWORD,WEB_UI_NAMESPACE,WEB_UI_COOKIE,';

  //username
  $disp_body .= '<p>Please enter your VPN account information for one provider.<br>'
                .'You may configure additional accounts after the setup wizard is finished.</p>';
  $disp_body .= "<table>\n";
  $disp_body .= '<tr><td>VPN Provider</td><td>';
    $disp_body .= '<select name="vpn_provider">';
    $disp_body .= '<option value="PIAtcp">PrivateInternetAccess.com</option>';
    $disp_body .= '<option value="FrootVPN">FrootVPN.com</option>';
    $disp_body .= '<option value="iVPN">iVPN.net</option>';
    $disp_body .= '<option value="HideIPVPNtcp">HideIPVPN.com</option>';
    $disp_body .= '</select>';
  $disp_body .= '</td>';
  $disp_body .= '<tr><td>VPN Account Username</td><td><input type="text" style="width: 15em" name="username" value="" placeholder="Your Account Username" required></td>';
  $disp_body .= '<tr><td>VPN Account Password</td><td><input type="password" style="width: 15em" name="password" value="" placeholder="Your Account Password" required></td>';
  $disp_body .= "</table>\n";
  $disp_body .= '<hr>';


  // Gateway
  $disp_body .= '<p>This virtual machine may act as a default gateway for your network and/or '
          .'a virtual private Lan.<br>'
          .'The public LAN is your network, where your DSL/Cable router is connected.</p>';
  $disp_body .= "<table>\n";
  $fields .= 'FORWARD_PUBLIC_LAN,';
  $sel = array(
            'id' => 'FORWARD_PUBLIC_LAN',
            'selected' =>  $settings['FORWARD_PUBLIC_LAN'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>VPN Gateway for public LAN</td><td>'.build_select($sel).'</td></tr>'."\n";
  $fields .= 'FORWARD_VM_LAN,';
  $sel = array(
            'id' => 'FORWARD_VM_LAN',
            'selected' =>  $settings['FORWARD_VM_LAN'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>VPN Gateway for VM LAN</td><td>'.build_select($sel).'</td></tr>'."\n";
  $disp_body .= "</table>\n";
  $disp_body .= '<hr>';



  // set a proper root password
  $disp_body .= '<p>Please enter a new root password below or accept the generated one.'
                .'You may reset the password at any time using the "Tools" menu.<br>'
                .'Passwords may not be less than 3 characters long!</p>';
  $disp_body .= '<br><strong>WARNING</strong> The console is set to a German QWERTZ keyboard layout. Run "dpkg-reconfigure keyboard-configuration" to change the layout. (only required if you login to command line)';
  $disp_body .= "<table>\n";
  $disp_body .= '<tr><td>root password</td><td><input type="text" style="width:25em;" name="new_root_password" value="'.$_pia->rand_string(50).'"></td></tr>'."\n";
  $disp_body .= "</table>\n";



  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= '<input type="hidden" name="store_fields" value="'.  rtrim($fields, ',').'">';
  $disp_body .= '</form>';
  $disp_body .= '</div>';
  $disp_body .= "<p>&nbsp;</p>\n";




  return $disp_body;

}
?>