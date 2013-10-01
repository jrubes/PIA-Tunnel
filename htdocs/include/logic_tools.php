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


function disp_default(){
  $disp_body = '';
  $disp_body .= disp_pia_update();
  $disp_body .= "<hr>";
  $disp_body .= disp_reset_pia();
  $disp_body .= "<hr>";
  $disp_body .= disp_update_root();
  $disp_body .= "<hr>";
  $disp_body .= disp_client_tools();
  $disp_body .= "<hr>";
  return $disp_body;
}

/**
 * returns UI elements in HTML
 * @return string string with HTML for body of this page
 */
function disp_client_tools(){
  $disp_body = '';

  //offer download links to client tools
  $disp_body .= '<p><a href="/monitor-windows.zip">Torrent Monitor for Windows</a><br>';
  $disp_body .= 'This script will detected port changes and will reconfigure your torrent'
                .' client with updated settings.'
                .'<br>Supports <a href="http://deluge-torrent.org/" target="_blank">Deluge</a> and <a href="http://www.qbittorrent.org/" target="_blank">qBittorrent</a>. Please check the documentation for instructions.'
                .'</p>';

  return $disp_body;
}

/**
 * returns UI elements in HTML
 * @return string string with HTML for body of this page
 */
function disp_pia_update(){
  $disp_body = '';

  //run pia-update on request
  $disp_body .= '<p><form class="inline" action="/?page=tools&cid=tools" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="run_pia_command">';
  $disp_body .= 'Here you may update the PIA Tunnel software. Active VPN connections may be terminated to apply new settings.';
  $disp_body .= '<br><input type="submit" name="pia-update" value="Start pia-update">';
  $disp_body .= "</form></p>\n";

  return $disp_body;
}

/**
 * returns UI elements in HTML
 * @return string string with HTML for body of this page
 */
function disp_update_root(){
  global $_pia;
  global $_token;
  $disp_body = '';

  $pass = array('update system root password');
  $tokens = $_token->pgen($pass);

  //change the root password
  $disp_body .= '<p><form class="inline" action="/?page=tools&cid=tools" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="run_pia_command">';
  $disp_body .= 'Here you may set a new root password. New passwords need to be at least three characters long.';
  $disp_body .= "<table>\n";
  $disp_body .= '<tr><td>root password</td><td><input type="text" style="width:30em;" name="new_root_password" value="'.$_pia->rand_string(50).'"></td></tr>'."\n";
  $disp_body .= "</table>\n";
  $disp_body .= '<input type="submit" name="update_root" value="Change root password">';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= "</form></p>\n";

  return $disp_body;
}

/**
 * returns UI elements in HTML
 * @return string string with HTML for body of this page
 */
function disp_reset_pia(){
  global $_token;
  $disp_body = '';

  $pass = array('complete system reset');
  $tokens = $_token->pgen($pass);

  $disp_body .= '<p><form class="inline" action="/?page=tools&cid=tools" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="run_pia_command">';
  $disp_body .= 'Here you may reset everything back to factory default and reboot the system.';
  $disp_body .= '<br><input type="submit" name="reset-pia" value="Reset to Default and Restart">';
  $disp_body .= '<input type="hidden" name="token" value="'.$tokens[0].'">';
  $disp_body .= "</form></p>\n";

  return $disp_body;
}
?>