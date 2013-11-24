<?php
/* execute tools for the PIA VPN GUI Management */
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */
/* @var $_token token */

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
  case 'update_software_client':
    global $meta;
    $meta['javascript'][] = '/js/UpdateClient.js';
    $disp_body .= disp_pia_update_client();
    break;
  case 'run_pia_command':
    if( array_key_exists('pia-update', $_POST) === true ){
      //GUI access to pia-setup
      $result = array();
      exec("sudo /pia/pia-update", $result);
      $disp_body .= "<div id=\"feedback\" class=\"feedback\">Update Executed</div>\n";
      $disp_body .= "<div id=\"feedback\" class=\"feedback\">\n";
      if( array_key_exists('0', $result) === true ){
        foreach( $result as $val ){
          $disp_body .= "$val<br>\n";
        }

      }
      $disp_body .= "</div>\n";

      break;

    }elseif( array_key_exists('reset-pia', $_POST) === true ){
      if( $_token->pval($_POST['token'], 'complete system reset') === true ){
        //reset repo and apply latest updates first
        exec("cd /pia ; git reset --hard HEAD");
        exec("sudo /pia/pia-update");

        //GUI access to reset-pia
        $result = array();
        exec("sudo /pia/reset-pia", $result);
        if( array_key_exists('0', $result) === true ){
          $_SESSION = array(); //clear all session vars
          $disp_body .= "<div id=\"feedback\" class=\"feedback\">Full system reset has been executed - system will reboot now.</div>\n";
          VM_restart();
        }
      }else{
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
      }

      $disp_body .= disp_default();
      break;

    }elseif( array_key_exists('update_root', $_POST) === true ){
      if( $_token->pval($_POST['token'], 'update system root password') === true ){
        $disp_body .= $_pia->update_root_password($_POST['new_root_password']);
      }else{
        $disp_body .= "<div id=\"feedback\" class=\"feedback\">Invalid token - request ignored.</div>\n";
      }
      $disp_body .= disp_default();
      break;
    }


  default :
    $disp_body .= disp_default();
}

$disp_body .= '<script type="text/javascript">'
				.'var timr1=setInterval(function(){'
					.'var _overview = new OverviewObj();'
					.'_overview.clean_feedback();'
				.'},2500);'
				.'</script>';


function disp_default(){
  $disp_body = '';
  $disp_body .= disp_docu();
  //$disp_body .= '<div class="clear"></div>';
  //$disp_body .= disp_pia_update();
  $disp_body .= '<div class="clear"></div>';
  $disp_body .= disp_client_tools();
  $disp_body .= '<div class="clear"></div>';
  $disp_body .= disp_update_root();
  $disp_body .= '<div class="clear"></div>';
  $disp_body .= disp_reset_pia();
  $disp_body .= '<div class="clear"></div>';
  return $disp_body;
}

/**
 * returns UI elements in HTML
 * @return string string with HTML for body of this page
 */
function disp_client_tools(){
  global $settings;
  $disp_body = '<div class="box tools">';
  $ret_arr = array();

  $if_LAN = $settings['IF_EXT'];
  $if_VLAN = $settings['IF_INT'];

  $ret = array();
  exec('/sbin/ip addr show '.$if_LAN.' | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  $ret_str .= "<tr><td>Public LAN IP</td><td id=\"public_ip\">$ret[0]</td></tr>";
  $ret_arr['lan_ip'] = $ret[0];
  unset($ret);

  $ret = array();
  exec('/sbin/ip addr show '.$if_VLAN.' | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  $ret_str .= "<tr><td>Public LAN IP</td><td id=\"public_ip\">$ret[0]</td></tr>";
  $ret_arr['vlan_ip'] = $ret[0];
  unset($ret);

  //offer download links to client tools
  $disp_body .= '<h2>Torrent Port Monitor</h2>';
  $disp_body .= 'Windows script to reconfigure your torrent client\'s config file on VPN port changes.'
                .' Supports <a href="http://deluge-torrent.org/" target="_blank">Deluge</a> and <a href="http://www.qbittorrent.org/" target="_blank">qBittorrent</a>. Please check <a href="/pia-tunnel_documentation.pdf" target="_blank">the documentation for instructions.</a>';
  $disp_body .= '<p><a href="http://'.$ret_arr['lan_ip'].'/monitor-windows.zip">Download from LAN</a>';
  $disp_body .= ' &nbsp; <a href="http://'.$ret_arr['vlan_ip'].'/monitor-windows.zip">Download from VM LAN</a></p>';

  $disp_body .= '</div>';
  return $disp_body;
}

/**
 * returns UI elements in HTML
 * @return string string with HTML for body of this page
 */
function disp_docu(){
  global $settings;
  $ret_arr = array();
  $disp_body = '<div class="box tools">';

  $if_LAN = $settings['IF_EXT'];
  $if_VLAN = $settings['IF_INT'];

  $ret = array();
  exec('/sbin/ip addr show '.$if_LAN.' | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  $ret_arr['lan_ip'] = $ret[0];
  unset($ret);

  $ret = array();
  exec('/sbin/ip addr show '.$if_VLAN.' | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  $ret_arr['vlan_ip'] = $ret[0];
  unset($ret);

  //offer download links to client tools
  $disp_body .= '<h2>Support &amp; Documentation</h2>';
  $disp_body .= 'Please consult the <a href="/pia-tunnel_documentation.pdf" target="_blank">documentation</a> before <a href="http://www.kaisersoft.net/index.php?p=5&amp;lang=eng&amp;subject=PIA-Tunnel%20Help%20Request" target="_blank">contacting support</a>. Thank you.';
  $disp_body .= '<br><br><a href="http://'.$ret_arr['lan_ip'].'/pia-tunnel_documentation.pdf" target="_blank">Open Documentation from LAN</a>';
  $disp_body .= ' &nbsp; <a href="http://'.$ret_arr['vlan_ip'].'/pia-tunnel_documentation.pdf" target="_blank">Open Documentation from VM LAN</a>';
  $disp_body .= '</div>';
  return $disp_body;
}

/**
 * returns UI elements in HTML
 * @return string string with HTML for body of this page
 */
function disp_pia_update(){
  $disp_body = '<div class="box tools">';

  $disp_body .= '<h2>Online Update</h2>';
  $disp_body .= '<form class="inline" action="/?page=tools&amp;cid=tools" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="run_pia_command">';
  $disp_body .= 'Download the latest updates from the <a href="https://github.com/KaiserSoft/PIA-Tunnel/tree/release_php-gui" target="_blank">GitHub repository.</a>';
  $disp_body .= '<br><br><input type="submit" name="pia-update" value="Start Online Update">';
  $disp_body .= "</form>\n";

  $disp_body .= '</div>';
  return $disp_body;
}

/**
 * returns UI element to handle update process
 * @return string string with HTML for body of this page
 */
function disp_pia_update_client(){
  global $_pia;

  $up = $_pia->get_update_status();
  if(is_int($up) === true && $up == 0 ){
    $up_txt = 'latest release';
  }elseif( $up > 0 ){
    $s = ( $up > 1 ) ? 's' : '';
    $up_txt = '<a href="/?page=tools&amp;cid=tools&amp;cmd=update_software_client">'."$up update{$s} available</a>";
  }else{
    $up_txt = $up;
  }

  $disp_body = '<div class="box update_client">';
  $disp_body .= '<h2>Online Update Client</h2>';
  $disp_body .= 'Updates are downloaded from the project\'s <a href="https://github.com/KaiserSoft/PIA-Tunnel/tree/release_php-gui" target="_blank">GitHub repository.</a>';
  $disp_body .= '<br>Update Status: '.$up_txt;

  $disp_body .= '<div class="clear"></div>';
  $disp_body .= '<p> </p>';
  $disp_body .= '<a id="toggle_git_updatelog" class="button" href="#" onclick="var _update = new UpdateClient(); _update.get_git_log('.$up.'); return false;">Show Update Log</a>';
  $disp_body .= ' <a id="toggle_git_log" class="button" href="#" onclick="var _update = new UpdateClient(); _update.get_git_log(50); return false;">Show Repository Log</a>';
  $disp_body .= '<div id="uc_feedback" ><textarea id="uc_feedback_txt">'.$_pia->git_log($up).'</textarea></div>';
  $disp_body .= '<div class="clear"></div>';

  $disp_body .= '<form class="inline" action="/?page=tools&amp;cid=tools" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="run_pia_command">';
  $disp_body .= '<br><input type="submit" id="pia-update" name="pia-update" value="Start Online Update">';
  $disp_body .= "</form>\n";

  $disp_body .= '<script type="text/javascript">';
  $disp_body .= '   var _update = new UpdateClient();';
  $disp_body .= '   _update.enhance_ui();';
  $disp_body .= '</script>';
  $disp_body .= '</div>';
  return $disp_body;
}

/**
 * returns UI elements in HTML
 * @return string string with HTML for body of this page
 */
function disp_update_root(){
  global $_pia;
  global $_token;
  $disp_body = '<div class="box tools">';

  $pass = array('update system root password');
  $tokens = $_token->pgen($pass);

  //change the root password
  $disp_body .= '<h2>Linux root Password</h2>';
  $disp_body .= '<form class="inline" action="/?page=tools&amp;cid=tools" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="run_pia_command">';
  $disp_body .= 'New passwords need to be at least three characters long.';
  $disp_body .= '<input type="text" class="extralong" name="new_root_password" value="'.$_pia->rand_string(35).'">'."\n";
  $disp_body .= '<input type="submit" name="update_root" value="Update Password">';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= "</form>\n";

  $disp_body .= '</div>';
  return $disp_body;
}

/**
 * returns UI elements in HTML
 * @return string string with HTML for body of this page
 */
function disp_reset_pia(){
  global $_token;
  $disp_body = '<div class="box tools">';

  $pass = array('complete system reset');
  $tokens = $_token->pgen($pass);
  $disp_body .= '<h2>Full System Reset</h2>';
  $disp_body .= '<strong>WARNING</strong> Deletes the cache, all settings, resets the repository and reboots the system.';
  $disp_body .= '<form class="inline" action="/?page=tools&amp;cid=tools" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="run_pia_command">';
  $disp_body .= ' <input type="submit" name="reset-pia" value="Execute System Reset">';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= "</form>\n";

  $disp_body .= '</div>';
  return $disp_body;
}
?>