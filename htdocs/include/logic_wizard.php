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
    break;
  case 'reset-system':
    $settings = $_settings->get_settings();

    if( $settings['HAS_BEEN_RESET'] != "yes" ){ //don't run again on page refresh
      //reset to HEAD then run update
      exec("cd /pia ; git reset --hard HEAD");
      exec("sudo /pia/pia-update");

      $result = array();
      exec("sudo /pia/reset-pia", $result);
      if( array_key_exists('0', $result) === true ){
        $_SESSION = array(); //clear all session vars
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Full system reset has been executed - system will reboot now.<br>Please wait about a minute before you reload the page.</div>\n";
        $_settings->save_settings('HAS_BEEN_RESET', "yes");
        VM_restart();
      }else{
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">FATAL ERROR when attempting to reset the system. This should never happen! Please contact support!</div>\n";
      }
    }else{
      $disp_body .= disp_wizard_default();
    }
    break;
  default:
    $settings = $_settings->get_settings();
    if( $settings['HAS_BEEN_RESET'] != "yes" ){
      $disp_body .= disp_wizard_reset();
    }else{
     $disp_body .= disp_wizard_default();
    }
    break;
}

/**
 * returns UI elements in HTML
 * @return string string with HTML for body of this page
 */
function disp_wizard_reset(){
  $disp_body = '';

  $disp_body .= '<p>Brand new setup detected!<br>';
  $disp_body .= 'The software needs to check for updates and reset a few system programs.<br>'
                .'This will take a few seconds to run and the VM will reboot to apply all changes.</p>'
                .'<p>Please be patient, this is for your protection.</p>';
  $disp_body .= '<p><form action="/?page=wizard&amp;cmd=reset-system" method="post">'."\n";
  $disp_body .= '<br><input type="submit" name="reset-pia" value="Prepare the System and Reboot">';
  $disp_body .= "</form></p>\n";

  return $disp_body;
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

  $disp_body .= '<div class="wizard_box">';
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
  $disp_body .= '<p>Please enter your <a href="https://www.privateinternetaccess.com" target="_blank">https://www.privateinternetaccess.com</a>
                    account information below. The information will be stored in /pia/login.conf with read access for root and this webUI.</p>';
  $disp_body .= "<table>\n";
  $disp_body .= '<tr><td>VPN Username</td><td><input type="text" style="width: 15em" name="username" value="" placeholder="Your Account Username" required></td>';
  $disp_body .= '<tr><td>VPN Password</td><td><input type="password" style="width: 15em" name="password" value="" placeholder="Your Account Password" required></td>';
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



  // Forwarding
  $disp_body .= '<p>This VM supports port forwarding to one IP on either LAN segment.<br>'
          .'You may share the VPN connection with multiple computers but port forwarding only works with'
          .' a single target IP.<br>'
          .'Enable the option below and select a VPN connection point marked with a * when creating a VPN connection later</p>';
  $disp_body .= "<table>\n";
  $fields .= 'FORWARD_PORT_ENABLED,';
  $sel = array(
          'id' => 'FORWARD_PORT_ENABLED',
          'selected' =>  $settings['FORWARD_PORT_ENABLED'],
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>Enable Port Forwarding</td><td>'.build_select($sel).'</td></tr>'."\n";
  $fields .= 'FORWARD_IP,';
  $disp_body .= '<tr><td>Forward IP</td><td><input type="text" name="FORWARD_IP" value="'.htmlspecialchars($settings['FORWARD_IP']).'"></td></tr>'."\n";
  $disp_body .= "</table>\n";
  $disp_body .= '<hr>';



  // pai-daemon
  $disp_body .= '<p>pia-daemon will mointor the VPN connections by pinging hosts through the VPN tunnel.<br>'
          .' It will block any traffic when an error is detected and will'
          .' attempt to reestablish a connection every few minutes.<br>'
          .' enable this option if you "just want your VPN to work"</p>';
  $disp_body .= "<table>\n";
  $fields .= 'DAEMON_ENABLED,';
  $sel = array(
            'id' => 'DAEMON_ENABLED',
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Enable pia-daemon</td><td>'.build_select($sel).'</td></tr>'."\n";
  $disp_body .= "</table>\n";
  $disp_body .= '<hr>';


  // set a proper root password
  $disp_body .= '<p>Please enter a new root password below or accept the generated one.'
                .'You may reset the password at any time using the "Tools" menu.<br>'
                .'Passwords may not be less than 3 characters long!</p>';
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