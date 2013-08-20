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
      $arg = escapeshellarg($_POST['vpn_connections']);
      
      //looks good, delete old session.log
      $f = '/pia/cache/session.log';
      $_files->rm($f);
      $c = "Connecting to $arg\n\n";
      $_files->writefile( $f, $c ); //write file so status overview works right away

      //time to initiate the connection
       //calling my bash scripts - this should work :)
      exec("sudo bash -c \"/pia/pia-start $arg &> /pia/cache/php_pia-start.log &\" &>/dev/null &"); //using bash allows this to happen in the background
      $_SESSION['connecting2'] = $arg; //store for messages
      
      $disp_body .= "<div class=\"feedback\">Establishing a VPN connection to $arg</div>\n";
      $disp_body .= disp_default();
    }
    break;
  case 'disconnect':
      //looks good, delete old session.log
      $_files->rm('/pia/cache/session.log');
      exec("sudo bash -c \"/pia/pia-stop &>/dev/null &\" &>/dev/null &"); //using bash allows this to happen in the background
      $_SESSION['connecting2'] = '';
      
      $disp_body .= "<div class=\"feedback\">Disconnecting VPN connection</div>\n";
      $disp_body .= disp_default();
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

  /* offer connect and disconnect buttons */
  $disp_body .= '<div><h2>Network Control</h2>';
  $disp_body .= '<form class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="page" value="">';
  $disp_body .= '<input type="hidden" name="cmd" value="connect">';
  $disp_body .= VPN_get_connections('vpn_connections');
  $disp_body .= '<input type="submit" name="connect_vpn" value="Connect VPN">'
                .'</form>';
  //disconnect button
  $disp_body .= '<form class="inline" action="/" method="post">';
  $disp_body .= '<input type="hidden" name="cmd" value="disconnect">';
  $disp_body .= '<input type="submit" name="disconnect_vpn" value="Disconnect VPN">'
                .'</form>';

  /* show network status */
  $disp_body .= '<h2>Network Status</h2>';
  $disp_body .= VM_get_status();
  $disp_body .= "</div>";  

  $disp_body .= "</div>";
  return $disp_body;
}
?>