<?php
/*
 * this page wil display a basic setup UI and store the settings when done
 */
/* @var $_settings PIASettings */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */



//act on $CMD variable
switch($_REQUEST['cmd']){
  case 'store':

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
  $settings = $_settings->get_settings();
  $disp_body = '';
  $fields = ''; //comma separate list of settings offered here

  $disp_body .= '<div class="wizard_box">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="dhcpd_settings">';
  $disp_body .= '<h2>PIA-Tunnel Setup Wizard</h2>'."\n";

  //username
  $disp_body .= '<p>Please enter your <a href="https://www.privateinternetaccess.com" target="_blank">https://www.privateinternetaccess.com</a>
                    account information below. The information will be stored in /pia/login.conf with read access for root and this webUI.</p>';
  $disp_body .= "<table>\n";
  $disp_body .= '<tr><td>Username</td><td><input type="text" style="width: 15em" name="username" value="" placeholder="Your Account Username"></td>';
  $disp_body .= '<tr><td>Password</td><td><input type="password" style="width: 15em" name="password" value="" placeholder="Your Account Password"></td>';
  $disp_body .= "</table>\n";


  // Gateway
  $disp_body .= "<p>&nbsp;</p>\n";
  $disp_body .= '<p>This virtual machine may act as a default gateway for your network and/or '
          .'a virtual private Lan. Set the IP of this system as the default gateway of the computers you want to '
          .'share the VPN connection with.<br>'
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



  // Forwarding
  $disp_body .= "<p>&nbsp;</p>\n";
  $disp_body .= '<p>This VM supports port forwarding and can forward the VPN port to one IP.<br>'
          .'You may share the VPN connection with multiple computers but port forwarding only works with'
          .' a single target IP.<br>Port forwarding is for torrent clients or other servers.<br>'
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



  // pai-daemon
  $disp_body .= "<p>&nbsp;</p>\n";
  $disp_body .= '<p>pia-daemon is a background script that can be started after a VPN connection '
          .' has been established.<br> The script will periodically check if the VPN connection is'
          .' still up and connect to a failover address when problems are detected.<br>'
          .' You may enable the daemon here and customize the list of failover connections later.</p>';
  $disp_body .= "<table>\n";
  $fields .= 'DAEMON_ENABLED,';
  $sel = array(
            'id' => 'DAEMON_ENABLED',
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Enable pia-daemon</td><td>'.build_select($sel).'</td></tr>'."\n";
  $disp_body .= "</table>\n";



  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= '</form>';
  $disp_body .= '</div>';
  $disp_body .= "<p>&nbsp;</p>\n";




  return $disp_body;

}
?>