<?php
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */

// only show this form if the user has logged in
$_auth->authenticate();

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
      if( $_token->pval($_POST['token'], 'handle user request - establish or disconnect VPN') === true ){
      //check if passed VPN name is valid and pass to command line if it is
      if( VPN_is_valid_connection($_POST['vpn_connections']) === true ){

        $_pia->pia_connect($_POST['vpn_connections']);
        $disp_body .= "<div class=\"feedback\">Establishing a VPN connection to $_POST[vpn_connections]</div>\n";
      }
      }else{
        $disp_body .= "<div class=\"feedback\">Invalid token - request ignored.</div>\n";
      }
      $disp_body .= disp_default();
      break;

    }elseif( array_key_exists('vpn_disconnect', $_POST) === true ){
      if( $_token->pval($_POST['token'], 'handle user request - establish or disconnect VPN') === true ){
        $_pia->pia_disconnect();
        $disp_body .= "<div class=\"feedback\">Disconnecting VPN</div>\n";
      }else{
        $disp_body .= "<div class=\"feedback\">Invalid token - request ignored.</div>\n";
      }

      $disp_body .= disp_default();
      break;

    }elseif( array_key_exists('daemon_start', $_POST) === true ){
      if( $_token->pval($_POST['token'], 'handle user request - start or stop pia-daemon') === true ){
        //start a VPN connection as with daemon => MYVPN[0]
        if( $_pia->is_vpn_up() === true ){
          //VPN already up, only start daemon
          //die('just daemon');
          $_pia->pia_daemon('start');
        }else{
          //VPN down, calling pia-start with "daemon" parameter
          $_pia->pia_connect('daemon');
        }
        $disp_body .= "<div class=\"feedback\">Starting pia-daemon</div>\n";
      }else{
        $disp_body .= "<div class=\"feedback\">Invalid token - request ignored.</div>\n";
      }


      $disp_body .= disp_default();
      break;

    }elseif( array_key_exists('daemon_stop', $_POST) === true ){
      if( $_token->pval($_POST['token'], 'handle user request - start or stop pia-daemon') === true ){
        //start a VPN connection as with daemon => MYVPN[0]
        $_pia->pia_daemon('stop');

        if( $_pia->status_pia_daemon() === 'offline' ){
          $disp_body .= "<div class=\"feedback\">pia-daemon has been stopped</div>\n";
        }else{
          $disp_body .= "<div class=\"feedback\">pia-daemon is still running. please try again</div>\n";
        }
      }else{
        $disp_body .= "<div class=\"feedback\">Invalid token - request ignored.</div>\n";
      }

      $disp_body .= disp_default();
      break;
    }

  case 'firewall_control':
    if( $_token->pval($_POST['token'], 'handle user request - start or stop the firewall') === true ){
      if( array_key_exists('firewall_enable', $_POST) === true ){
        $_services->firewall_fw('stop');
        $_services->firewall_fw('start');
        $disp_body .= "<div class=\"feedback\">Firewall has been restarted</div>\n";

      }elseif( array_key_exists('firewall_disable', $_POST) === true ){
        $_services->firewall_fw('stop');
        $disp_body .= "<div class=\"feedback\">Forwarding has been stopped</div>\n";
      }
    }else{
      $disp_body .= "<div class=\"feedback\">Invalid token - request ignored.</div>\n";
    }

    $disp_body .= disp_default();
    break;

  case 'os_control':
    if( $_token->pval($_POST['token'], 'handle user request - shutdown or reboot the OS') === true ){
      if( array_key_exists('vm_shutdown', $_POST) === true ){
        VM_shutdown();
        $disp_body .= "<div class=\"feedback\">The System is about to shut down</div>\n";
        break;
      }elseif( array_key_exists('vm_restart', $_POST) === true ){
        VM_restart();
        $disp_body .= "<div class=\"feedback\">The System will reboot now.</div>\n";
        break;
      }
    }else{
      $disp_body .= "<div class=\"feedback\">Invalid token - request ignored.</div>\n";
      $disp_body .= disp_default();
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
  global $_token;
  $disp_body = '';
  /* show VM network and VPN overview */

  //VPN control UI
  $disp_body .= '<h2>Network Control</h2>';

  $pass = array('handle user request - start or stop pia-daemon');
  $tokens = $_token->pgen($pass);
  $disp_body .= '<form class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="network_control">';
  $disp_body .= '<table class="control_box">';
  $disp_body .= '<tr>';
  $disp_body .= '<td>PIA VPN Daemon</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" style="width: 9em;" name="daemon_start" value="Start pia-daemon">';
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" style="width: 9em;" name="daemon_stop" value="Stop pia-daemon">';
  $disp_body .= '</td>';
  $disp_body .= '</tr>';
  $disp_body .= '</table>';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= " </form>\n";

  $pass = array('handle user request - establish or disconnect VPN');
  $tokens = $_token->pgen($pass);
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
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= " </form>\n";

  //firewall control UI
  $pass = array('handle user request - start or stop the firewall');
  $tokens = $_token->pgen($pass);
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
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= "</form>\n";

  //OS control UI
  $pass = array('handle user request - shutdown or reboot the OS');
  $tokens = $_token->pgen($pass);
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
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= "</form>\n";




  /* show network status */
  $disp_body .= '<h2>Network Status</h2>';
  $disp_body .= '<div id="network_status">'.VM_get_status().'</div>';

  $disp_body .= '<script type="text/javascript">'
                .'var timr1=setInterval(function(){'
                  .'var _overview = new OverviewObj();'
                  .'_overview.refresh_status();'
                .'},5000);'
                .'</script>';

  return $disp_body;
}
?>