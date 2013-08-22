<?php
/* execute tools for the PIA VPN GUI Management */
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
  case 'run_pia_command':
    if( array_key_exists('pia-update', $_POST) === true ){
      //GUI access to pia-setup
      exec("sudo bash -c \"/pia/pia-update &> /dev/null &\" &>/dev/null &"); //using bash allows this to happen in the background
      $disp_body .= "<div class=\"feedback\">Running pia-update - this may take a few minutes....</div>\n";
      $disp_body .= disp_default();
      break;

    }elseif( array_key_exists('reset-pia', $_POST) === true ){
      //GUI access to reset-pia
      $result = array();
      exec("sudo /pia/reset-pia", $result); //using bash allows this to happen in the background
      if( array_key_exists('0', $result) === true ){
        $disp_body .= "<div class=\"feedback\">Full system reset has been executed. Please reboot or shutdown.</div>\n";
      }

      $disp_body .= disp_default();
      break;
    }


  default :
    $disp_body .= disp_default();
}


  //run pia-update on request
  $disp_body .= '<form class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="run_pia_command">';
  $disp_body .= '<table class="control_box">';
  $disp_body .= '<tr>';
  $disp_body .= '<td>';
  $disp_body .=   "pia-update control\n";
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" style="width: 9em;" name="pia-update" value="Start pia-update">';
  $disp_body .= '</td>';
  $disp_body .= '<td>';
  $disp_body .= ' <input type="submit" style="width: 9em;" name="reset-pia" value="*Danger* Start pia-update">';
  $disp_body .= '</td>';
  $disp_body .= '</tr>';
  $disp_body .= '</table>';
  $disp_body .= "</form>\n";
?>