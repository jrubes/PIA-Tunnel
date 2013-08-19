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
  case 'connect':
    //check if passed VPN name is valid and pass to command line if it is
    if( VPN_is_valid_connection($_POST['vpn_connections']) === true ){
      //looks good, pass along
      $arg = escapeshellarg($_POST['vpn_connections']);
      exec("/pia/pia-connect \"$arg\" > /dev/null 2>/dev/null &"); //calling my bash scripts - this should work :)
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
  $disp_body .= '<div><h2>Network Status</h2>';
  $disp_body .= VM_get_status();
  $disp_body .= "</div>";


  /* offer connect and disconnect buttons */
  $disp_body .= '<div><h2>Network Control</h2>';
  $disp_body .= '<form class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="page" value="">';
  $disp_body .= '<input type="hidden" name="cmd" value="connect">';
  $disp_body .= VPN_get_connections();
  $disp_body .= '<input type="submit" name="connect_vpn" value="Connect VPN">'
                .'</form>';
  //disconnect
  $disp_body .= '<form class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="disconnect">';
  $disp_body .= '<input type="submit" name="disconnect_vpn" value="Disconnect VPN">'
                .'</form>';


  $disp_body .= "</div>";
  return $disp_body;
}
?>