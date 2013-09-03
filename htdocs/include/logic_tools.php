<?php
/* execute tools for the PIA VPN GUI Management */
/* @var $_settings PIASettings */
/* @var $_pia PIACommands */
/* @var $_files FilesystemOperations */
/* @var $_services SystemServices */


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
      $disp_body .= "<div class=\"feedback\">Update Executed</div>\n";
      $disp_body .= "<div class=\"feedback\">\n";
      if( array_key_exists('0', $result) === true ){
        foreach( $result as $val ){
          $disp_body .= "$val<br>\n";
        }

      }
      $disp_body .= "</div>\n";

      break;

    }elseif( array_key_exists('reset-pia', $_POST) === true ){
      //GUI access to reset-pia
      $result = array();
      exec("sudo /pia/reset-pia", $result);
      if( array_key_exists('0', $result) === true ){
        $_SESSION = array(); //clear all session vars
        $disp_body .= "<div class=\"feedback\">Full system reset has been executed - system will reboot now.</div>\n";
        VM_restart();
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
  $disp_body .= disp_reset_pia();
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
function disp_reset_pia(){
  $disp_body = '';

  $disp_body .= '<p><form class="inline" action="/?page=tools&cid=tools" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="run_pia_command">';
  $disp_body .= 'Here you may reset everything back to factory default and reboot the system.';
  $disp_body .= '<br><input type="submit" name="reset-pia" value="Reset to Default and Restart">';
  $disp_body .= "</form></p>\n";

  return $disp_body;
}
?>