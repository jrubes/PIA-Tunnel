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
        //$disp_body .= "<div id=\"feedback\" class=\"feedback\">Establishing a VPN connection to $_POST[vpn_connections]</div>\n";
      }
      }else{
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
      }
      $disp_body .= disp_default();
      break;

    }elseif( array_key_exists('vpn_disconnect', $_POST) === true ){
      if( $_token->pval($_POST['token'], 'handle user request - establish or disconnect VPN') === true ){
        $_services->socks_stop();
        $_pia->pia_disconnect();
        //$disp_body .= "<div id=\"feedback\" class=\"feedback\">Disconnecting VPN</div>\n";
      }else{
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
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
        //$disp_body .= "<div id=\"feedback\" class=\"feedback\">Starting pia-daemon</div>\n";
      }else{
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
      }


      $disp_body .= disp_default();
      break;

    }elseif( array_key_exists('daemon_stop', $_POST) === true ){
      if( $_token->pval($_POST['token'], 'handle user request - start or stop pia-daemon') === true ){
        //start a VPN connection as with daemon => MYVPN[0]
        $_pia->pia_daemon('stop');

        if( $_pia->status_pia_daemon() === 'offline' ){
          //$disp_body .= "<div id=\"feedback\" class=\"feedback\">pia-daemon has been stopped</div>\n";
        }else{
          $disp_body .= "<div id=\"feedback\" class=\"feedback\">pia-daemon is still running. please try again</div>\n";
        }
      }else{
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
      }

      $disp_body .= disp_default();
      break;
    }

  case 'socks_proxy_control':
    if( $_token->pval($_POST['token'], 'handle user request - start or restart SOCKS proxy') === true ){
      if( array_key_exists('socks_start', $_POST) === true ){
        if( $_services->socks_status() === 'running' ){

          if( $_services->socks_start() === true )
          {
            $disp_body .= "<div id=\"feedback\" class=\"feedback\">SOCKS 5 Proxy has been restarted</div>\n";

            $_services->firewall_fw('stop');
            $_services->firewall_fw('start');
            $disp_body .= "<div id=\"feedback\" class=\"feedback\">Firewall has been restarted</div>\n";
          }else{
            $disp_body .= "<div id=\"feedback\" class=\"feedback\">ERROR: unable to (re)start SOCKS 5 Proxy in 'running'. Please send the following to the devloper: return of socks-status.sh: ".$_services->socks_status()."</div>\n";
          }

        }elseif( $_services->socks_status() === 'not running'){

          if( $_services->socks_start() === true )
          {
            $disp_body .= "<div id=\"feedback\" class=\"feedback\">SOCKS 5 Proxy has been started</div>\n";

            $_services->firewall_fw('stop');
            $_services->firewall_fw('start');
            $disp_body .= "<div id=\"feedback\" class=\"feedback\">Firewall has been restarted</div>\n";
          }else{
            $disp_body .= "<div id=\"feedback\" class=\"feedback\">ERROR: unable to (re)start SOCKS 5 Proxy in 'not running'. Please send the following to the devloper: return of socks-status.sh: ".$_services->socks_status()."</div>\n";
          }

        }else{
          $disp_body .= "<div id=\"feedback\" class=\"feedback\">ERROR: unable to (re)start SOCKS 5 Proxy. Please send the following to the devloper: return of socks-status.sh: ".$_services->socks_status()."</div>\n";
        }


      }elseif( array_key_exists('socks_stop', $_POST) === true ){
        $_services->socks_stop();
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">SOCKS 5 Proxy has been stopped</div>\n";

        $_services->firewall_fw('stop');
        $_services->firewall_fw('start');
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Firewall has been restarted</div>\n";
      }
    }else{
      $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
    }

    $disp_body .= disp_default();
    break;


  case 'firewall_control':
    if( $_token->pval($_POST['token'], 'handle user request - start or stop the firewall') === true ){
      if( array_key_exists('firewall_enable', $_POST) === true ){
        $_services->firewall_fw('stop');
        $_services->firewall_fw('start');
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Firewall has been restarted</div>\n";

      }elseif( array_key_exists('firewall_disable', $_POST) === true ){
        $_services->firewall_fw('stop');
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Forwarding has been stopped</div>\n";
      }
    }else{
      $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
    }

    $disp_body .= disp_default();
    break;

  case 'os_control':
    if( $_token->pval($_POST['token'], 'handle user request - shutdown or reboot the OS') === true ){
      if( array_key_exists('vm_shutdown', $_POST) === true ){
        VM_shutdown();
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">VM is shutting down....</div>\n";
        break;
      }elseif( array_key_exists('vm_restart', $_POST) === true ){
        VM_restart();
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Rebooting VM....</div>\n";
        break;
      }
    }else{
      $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
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
  global $settings;
  $disp_body = '';
  /* show VM network and VPN overview */

  //VPN control UI
  $disp_body .= '<noscript><p>please enable javascript to activate the advanced UI</p></noscript>';
  $disp_body .= '<div id="overview_net_control">';
  $disp_body .= '<h2>Network Control</h2>';

  $pass = array('handle user request - establish or disconnect VPN');
  $tokens = $_token->pgen($pass);
  $disp_body .= '<form id="frm_vpn_connection" class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="network_control">';
  $disp_body .= '<table class="control_box">';
  $disp_body .= '<tr>';
  $disp_body .= '<td>';
  $disp_body .=   VPN_get_connections('vpn_connections', array( 'initial' => 'Connect To', 'onchange' => 'vpn_connect();'))."\n"; // Connect VPN
  $disp_body .= '</td>';
  $disp_body .= '<td id="ele_vpn_connect">';
  $disp_body .= ' <input type="submit" name="vpn_connect" value="Connect VPN">';
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" name="vpn_disconnect" value="Disconnect VPN">';
  $disp_body .= '</td>';
  $disp_body .= '</tr>';
  $disp_body .= '</table>';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= " </form>\n";

  $pass = array('handle user request - start or stop pia-daemon');
  $tokens = $_token->pgen($pass);
  $disp_body .= '<form class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="network_control">';
  $disp_body .= '<table class="control_box">';
  $disp_body .= '<tr>';
  $disp_body .= '<td id="ele_daemon_lbl">PIA VPN Daemon</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" name="daemon_start" value="Start pia-daemon">';
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" name="daemon_stop" value="Stop pia-daemon">';
  $disp_body .= '</td>';
  $disp_body .= '</tr>';
  $disp_body .= '</table>';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= " </form>\n";

  if( $settings['SOCKS_INT_ENABLED'] == 'yes' || $settings['SOCKS_EXT_ENABLED'] == 'yes' )
  {
    $pass = array('handle user request - start or restart SOCKS proxy');
    $tokens = $_token->pgen($pass);
    $disp_body .= '<form id="frm_socks_proxy" class="inline" action="/" method="post">';
    $disp_body .= '<input type="hidden" name="cmd" value="socks_proxy_control">';
    $disp_body .= '<table class="control_box">';
    $disp_body .= '<tr>';
    $disp_body .= '<td id="ele_socks_lbl">';
    $disp_body .=   "SOCKS 5 Proxy\n";
    $disp_body .= '</td>';
    $disp_body .= '</td>';
    $disp_body .= '<td>';
    $disp_body .= ' <input type="submit" name="socks_start" value="Start Proxy Server">';
    $disp_body .= '</td>';
    $disp_body .= '<td>';
    $disp_body .= ' <input type="submit" name="socks_stop" value="Stop Proxy Server">';
    $disp_body .= '</td>';
    $disp_body .= '</tr>';
    $disp_body .= '</table>';
    $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
    $disp_body .= " </form>\n";
  }

  //firewall control UI
  $pass = array('handle user request - start or stop the firewall');
  $tokens = $_token->pgen($pass);
  $disp_body .= '<form class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="firewall_control">';
  $disp_body .= '<table class="control_box">';
  $disp_body .= '<tr>';
  $disp_body .= '<td id="ele_firewall_lbl">';
  $disp_body .=   "Firewall\n";
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" name="firewall_enable" value="Restart Firewall">';
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" name="firewall_disable" value="Stop Forwarding">';
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
  $disp_body .= '<td>&nbsp;</td><td>&nbsp;</td>'; //empty row to move buttons out of the way
  $disp_body .= '</tr>';
  $disp_body .= '<tr>';
  $disp_body .= '<td id="ele_os_lbl">';
  $disp_body .=   "Operating System\n";
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" name="vm_restart" value="Restart PIA-VM">';
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" name="vm_shutdown" value="Shutdown PIA-VM">';
  $disp_body .= '</td>';
  $disp_body .= '</tr>';
  $disp_body .= '</table>';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= "</form>\n";
  $disp_body .= "</div>\n";




  /* show system status */
  $disp_body .= '<div id="overview_net_status">';
  $disp_body .= '<h2>System Status</h2>';
  $disp_body .= '<div id="system_status">'.VM_get_status().'</div>';
  $disp_body .= "</div>\n";

  $disp_body .= '<script type="text/javascript">'
                .'var timr1=setInterval(function(){'
                  .'var _overview = new OverviewObj();'
                  .'_overview.refresh_status();'
                  .'_overview.clean_feedback();'
                  .'},10000);'

                  .'var _overview = new OverviewObj();'
                  .'_overview.set_js_network_control();'
                .'/* handle the "connect" event on the overview page */
                    function vpn_connect(){
                    var submit_form = document.getElementById(\'frm_vpn_connection\');
                    var ele_conn = document.createElement((\'input\'));
                    ele_conn.setAttribute(\'type\', \'hidden\');
                    ele_conn.setAttribute(\'name\', \'vpn_connect\');
                    submit_form.appendChild(ele_conn);
                    submit_form.submit();
                  }'
                .'</script>';

  return $disp_body;
}
?>