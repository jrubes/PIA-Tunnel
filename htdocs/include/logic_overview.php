<?php
unset($_SESSION['ovpn']); //dev

/* load list of available connections into SESSION */
if(array_key_exists('ovpn', $_SESSION) !== true ){
  if( VPN_ovpn_to_session() !== true ){
    echo "FATAL ERROR: Unable to get list of VPN connections!";
    return false;
  }
}



//act on $CMD variable
switch($_REQUEST['cmd']){
  case 'network_control':
    if( array_key_exists('vpn_connect', $_POST) === true ){
      //check if passed VPN name is valid and pass to command line if it is
      if( VPN_is_valid_connection($_POST[md5('vpn_connections')]) === true ){
        $arg = escapeshellarg($_POST[md5('vpn_connections')]);

        //looks good, delete old session.log
        $f = '/pia/cache/session.log';
        $_files->rm($f);
        $c = "connecting to $arg\n\n";
        $_SESSION['connecting2'] = $arg; //store for messages
        $_files->writefile( $f, $c ); //write file so status overview works right away

        //time to initiate the connection
        exec("sudo bash -c \"/pia/pia-start $arg &> /pia/cache/php_pia-start.log &\" &>/dev/null &"); //using bash allows this to happen in the background
        $disp_body .= "<div class=\"feedback\">Establishing a VPN connection to $arg</div>\n";
        $disp_body .= disp_default();
      }
      break;

    }elseif( array_key_exists('vpn_disconnect', $_POST) === true ){
      //looks good, delete old session.log
      $_files->rm('/pia/cache/session.log');
      exec("sudo bash -c \"/pia/pia-stop &>/dev/null &\" &>/dev/null &"); //using bash allows this to happen in the background
      $_SESSION['connecting2'] = '';

      $disp_body .= "<div class=\"feedback\">Disconnecting VPN</div>\n";
      $disp_body .= disp_default();
      break;
    }

  case 'firewall_control':
    if( array_key_exists('firewall_enable', $_POST) === true ){
      VPN_forward('stop');
      VPN_forward('start');
      $disp_body .= "<div class=\"feedback\">Firewall has been restarted</div>\n";

    }elseif( array_key_exists('firewall_disable', $_POST) === true ){
      VPN_forward('stop');
      $disp_body .= "<div class=\"feedback\">Forwarding has been stopped</div>\n";
      $disp_body .= disp_default();
    }
    $disp_body .= disp_default();
    break;

  case 'os_control':
    if( array_key_exists('vm_shutdown', $_POST) === true ){
      VM_shutdown();
      $disp_body .= "<div class=\"feedback\">The System is about to shut down</div>\n";
      break;
    }elseif( array_key_exists('vm_restart', $_POST) === true ){
      VM_restart();
      $disp_body .= "<div class=\"feedback\">The System is about to restart</div>\n";
      break;
    }
    break;

  default :
    $disp_body .= disp_default();
}



















/* FUNCTIONS - move into functions file later */


/**
 * returns the default UI for this page
 * @return string string with HTML for body of this page
 */
function disp_default(){
  $disp_body = '';
  /* show VM network and VPN overview */

  //VPN control UI
  $disp_body .= '<h2>Network Control</h2>';
  $disp_body .= '<form class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="network_control">';
  $disp_body .= '<table class="control_box">';
  $disp_body .= '<tr>';
  $disp_body .= '<td>';
  $disp_body .=   VPN_get_connections('vpn_connections')."\n";
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" style="width: 9em;" name="vpn_connect" value="Connect VPN">';
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" style="width: 9em;" name="vpn_disconnect" value="Disconnect VPN">';
  $disp_body .= '</td>';
  $disp_body .= '</tr>';
  $disp_body .= '</table>';
  $disp_body .= " </form>\n";

  //firewall control UI
  $disp_body .= '<form class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="firewall_control">';
  $disp_body .= '<table class="control_box">';
  $disp_body .= '<tr>';
  $disp_body .= '<td>';
  $disp_body .=   "Firewall control\n";
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" style="width: 9em;" name="firewall_enable" value="Restart Firewall">';
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" style="width: 9em;" name="firewall_disable" value="Stop Forwarding">';
  $disp_body .= '</td>';
  $disp_body .= '</tr>';
  $disp_body .= '</table>';
  $disp_body .= "</form>\n";

  //OS control UI
  $disp_body .= '<form class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="os_control">';
  $disp_body .= '<table class="control_box">';
  $disp_body .= '<tr>';
  $disp_body .= '<td>';
  $disp_body .=   "OS control\n";
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" style="width: 9em;" name="vm_restart" value="Restart PIA-VM">';
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" style="width: 9em;" name="vm_shutdown" value="Shutdown PIA-VM">';
  $disp_body .= '</td>';
  $disp_body .= '</tr>';
  $disp_body .= '</table>';
  $disp_body .= "</form>\n";




  /* show network status */
  $disp_body .= '<h2>Network Status</h2>';
  $disp_body .= VM_get_status();

  return $disp_body;
}
?>