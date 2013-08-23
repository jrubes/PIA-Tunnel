<?php
unset($_SESSION['ovpn']); //dev
unset($_SESSION['settings.conf']);

/* load list of available connections into SESSION */
if(array_key_exists('ovpn', $_SESSION) !== true ){
  if( VPN_ovpn_to_session() !== true ){
    echo "<div class=\"feedback\">FATAL ERROR: Unable to get list of VPN connections!</div>\n";
    return false;
  }
}


//act on $CMD variable
switch($_REQUEST['cmd']){
  case 'vpn':
    $disp_body .= disp_vpn_default();
    break;
  case 'vpn_store';
    //update user settings
    $disp_body .= update_user_settings();
    //show inout forms again
    $disp_body .= disp_vpn_default();
    break;

  case 'network':
    $disp_body .= disp_network_default();
    $disp_body .= disp_dhcpd_default();
    break;

  case 'network_store';
    //restart the network or store settings
    if( array_key_exists('restart_firewall', $_POST ) === true && $_POST['restart_firewall'] != '' ){
      VPN_forward('stop');
      VPN_forward('start');
      $disp_body .= "<div class=\"feedback\">Firewall has been started</div>\n";
    }else{
      //update user settings
      $disp_body .= update_network_settings();
    }

    //show inout forms again
    $disp_body .= disp_network_default();
    break;

  case 'store_setting':
    //settings are now stored section by section.
    // this will allow me to restart the network on network changes and so on.
    // $_POST['store'] indicates which settings need to be stored

    switch( $_POST['store'] ){
      case 'dhcpd_settings':
        //dhcpd settings will store new settings to settings.conf
        if( VPN_save_settings() === true ){
          //then load a dhcpd.conf template from /pia/include/dhcpd.conf
          //apply the changes and write the new config file back to /etc/dhcp/dhcpd.conf

          $template = dhcpd_process_template();
          var_dump($template);
          die();

        }else{
          $disp_body .= "<div class=\"feedback\">Request to store settings but nothing was changed.</div>\n";
          $disp_body .= disp_network_default();
        }



        break;
    }
    break;

  default :
    $disp_body .= '<h2>Please select a menu option.</h2>';
}










/* FUNCTIONS - move into functions file later */

/**
 * main function to store settings in /pia/settings.conf
 * loop over all settings in settings.conf and look for a matching $_POST
 * settings are md5() summed in POST to avoid
 * array issues with PHP when posting a string like FOO[0]
 * @global object $_files
 * @return boolean TRUE on success or FALSE when nothing was changed
 */
function VPN_save_settings(){
  global $_files;
  $settings = VPN_get_settings();
  $updated_one=false;

  //handle regular strings here
  foreach( $settings as $setting_key => $setting_val ){

    //arrays need to be stored different
    if( VPN_is_settings_array( $setting_key ) === false )
    {
      //# Regular values for settings.conf
      $hash = md5($setting_key); //hash the key to avoid array issues with PHP
      if( array_key_exists($hash, $_POST) === true && $setting_val != $_POST[$hash] ){
        //setting found and setting has changed, UPDATE!
        $k = escapeshellarg($setting_key);
        $v = escapeshellarg($_POST[$hash]);
        exec("/pia/pia-settings $k $v");
        echo "$k is now $v<br>\n"; //dev stuff
        $updated_one=true;
      }
    }//if( VPN_is_settings_array
  }

  //# array values for settings.conf #
  //get a list of storage arrays from $_POST
  $post_storage_array = VPN_get_post_storage_arrays();
  foreach( $post_storage_array as $post_value ){
    echo "processing $post_value<br>";
    $array_setting = VPN_get_settings_array($post_value); //get only the array we are processing now
    if( $array_setting != false ){
      foreach( $array_setting as $akey => $aval ){
        //$akey now contains the name of the array in settings.conf as string
        // so $akey='foo[0]' and $aval contains the settings value
        // in settings.conf foo[0]=$aval

        //see if one of the array values changes
        $hash = md5($akey);
        if( $_POST[$hash] !== $aval ) {
          //one of an array setting has been changed. I don't want to update the array
          //so let's just write the current values into settings.con
          //echo "Set changed: $akey old: $aval new: {$_POST[$hash]}";
          echo "Removed array values for $post_value<br>\n";
          VPN_setting_remove_array($post_value); //remove array from settings.conf

          //now add all posted values back in
          exec("/pia/pia-settings $k $v");
        }else{
          $m5 = md5($akey);
          echo "no match! ak: $akey = pk: $_POST[$m5] && av: $aval = pv: $post_value<br>";
        }


      }
    }
  }

die();


  if( $updated_one === true ){
    return true;
  }else{
    return false;
  }
}


/**
 * function to scan $_POST for any settings.conf array values
 *  Warning: function very loopy - needs to be optimized
 * @param string $match=null *optional* name of "$_POST key string array" to return - get all if null
 * @return array,bool Array containing one storage array name per key or FALSE if none where found
 */
function VPN_get_post_storage_arrays($match=null){
  $settings = VPN_get_settings();
  $ret = array();

  reset($_POST);
  foreach( $_POST as $key => $val ){

    //$_POST keys are md5() of the setting names so loop over $settings to find a match
    reset($settings);
    $found = false;
    foreach( $settings as $set_key => $set_val ){
      $hash = md5($set_key);
      if( $hash === $key ){
        $found = true;
        break;
      }
    }

    if( $found === true ){
      if(VPN_is_settings_array($set_key) === true ){
        $name_only = substr($set_key, 0, strpos($set_key, '[') ); //get only the array name, without key, from $set_key string
        //this is an array, do we know this key already?
        if( array_is_value_unique($ret, $name_only) === false ){
          $ret[] = $name_only;
        }
      }
    }
  }

  if( count($ret) == 0 ){ return false; }
  else{ return $ret; }
}


/**
 * function to check if $val is alread stored in the array
 * @param array $ar the array to check in
 * @param string $val the value to look for
 * @return boolean true if $val is already in the array, false if not
 */
function array_is_value_unique( &$ar, $val ){
  reset($ar);
  foreach( $ar as $key => $array_val ){
    if( $array_val == $val ){
      return true;
    }
  }
  return false;
}

/**
 * method to check if $config_value is part of a settings array == contains [x]
 * so passing 'FOO[99]' returns true while 'FOO' will not
 * @param string $config_value string containing name of config value
 * @return boolean TRUE when string is an array in settings.conf or FALSE if not
 */
function VPN_is_settings_array( $config_value ){
  //arrays contain [] so check for both
  $b_open = strpos($config_value, '[');
  $b_close = strpos($config_value, ']');
  $key = (int)substr($config_value, $b_open+1, (strlen($config_value)-$b_close) ); //get only the array key

  if( $b_open != 0 && $b_close != 0 ){
    //no ensure that [ comes before ]
    if( $b_open < $b_close ){
      //assemble different parts back together to check script logic
      //$assembled will have to == $config_value
      $assembled = substr($config_value, 0, $b_open).'['.$key.']';
      if( $assembled !== $config_value ){
        die('FATAL SCRIPT ERROR 45d: bad logic! Please contact support.'.$assembled.' does not match '.$config_value);
      }
      return true;
    }
  }
  return false;
}

/**
 * function to modify /pia/include/dhcpd.conf in RAM and return the changes
 * @global object $_files
 * @return string,bool string containing the modified dhcpd.conf file or false on error
 */
function dhcpd_process_template(){
  global $_files;
  $templ = $_files->readfile('/pia/include/dhcpd.conf');
  $settings = VPN_get_settings();

  $SometimesIreallyHatePHP = 1; //passing this int bý reference will save tremendous ammounts of RAM - AWESOME SHIT!
  $templ = str_replace('SUBNET_IP_HERE', $settings['DHCPD_SUBNET'], $templ, $SometimesIreallyHatePHP);
  $templ = str_replace('NETWORK_MASK_HERE', $settings['DHCPD_MASK'], $templ, $SometimesIreallyHatePHP);
  $templ = str_replace('IP_RANGE_HERE', $settings['DHCPD_RANGE'], $templ, $SometimesIreallyHatePHP);
  $templ = str_replace('BROADCAST_HERE', $settings['DHCPD_BROADCAST'], $templ, $SometimesIreallyHatePHP);

  //router IP is the IP of eth1, go get it
  $ret = array();
  exec('/sbin/ip addr show eth1 | grep -w "inet" | gawk -F" " \'{print $2}\' | cut -d/ -f1', $ret);
  if(array_key_exists('0', $ret) ){
    $templ = str_replace('ROUTER_IP_HERE', $ret[0], $templ, $SometimesIreallyHatePHP);
  }

  // NAMESERVERS is an array which may contain multiple entries, loop over it
  $NAMESERVERS = VPN_get_settings_array('NAMESERVERS');
  $ins_dns = '';
  foreach( $NAMESERVERS as $DNS){
    $ins_dns .= ($ins_dns === '' ) ? $DNS : ", $DNS";
  }
  $templ = str_replace('DNSSERVER_HERE', $ins_dns, $templ, $SometimesIreallyHatePHP);

  //all done - return
  return $templ;

}


/**
 * method to update the settings.conf it will loop over the settings and check for matchin $_POSTs
 * @global object $_files
 */
function update_network_settings(){
  global $_files;
  $upcnt = 0;
  $settings = VPN_get_settings();

  $disp_body = '';
  foreach( $settings as $key => $val ){
    $hash = md5($key); //hash the key to avoid array issues with PHP
    if( array_key_exists($hash, $_POST) === true && $val != $_POST[$hash] ){

      //update setting
      $k = escapeshellarg($key);
      $v = escapeshellarg($_POST[$hash]);
      exec("/pia/pia-settings $k $v");
      //$disp_body .= "$k is now $v<br>\n"; //dev stuff
      ++$upcnt;

    }elseif( array_key_exists($hash.'_del', $_POST) === true ){
        //delete this setting from the file
        $disp_body .= "<div class=\"feedback\">delete not yet implemented</div>\n";
    }elseif( array_key_exists($hash.'_combined', $_POST) === true ){
        //store array values passed comma separated

        /* remove old values */
        $ret =  array();
        //get line numbers of current settings
        $config_value = substr($key, 0, strpos($key, '[') ); //this is the value of $key without [n]. this is used for the array name when writing it back
        exec('grep -n  "'.$config_value.'" /pia/settings.conf | cut -d: -f1', $ret); // $ret[] will contain line number with current settings

        //loop over returned values and remove the lines
        for( $x = count($ret)-1 ; $x >= 0 ; --$x ){ //go backwards or line numbers need to be adjusted
          $hhh = array();
          exec('sed "'.$ret[$x].'d" /pia/settings.conf > /pia/settings.conf.back');
          exec('mv /pia/settings.conf.back /pia/settings.conf');
          //echo 'sed "'.$ret[$x].'d" /pia/settings.conf > /pia/settings.conf'.'<br>';
          ++$upcnt;
        }

        //now add the settings back at the bottom of the file
        $values = explode(',', $_POST[$hash.'_combined']); // "combined" is comma separated so explode by it
        for( $x = 0 ; $x < count($values) ; ++$x ){ //yes count in a loop - only doing it since this is a single user script -- ohh yeah, sue me!
          //echo("echo '".$config_value.'['.$x."]=\"".$values[$x]."\"' >> '/pia/settings.conf'".'<br>');
          exec("echo '".$config_value.'['.$x."]=\"".$values[$x]."\"' >> '/pia/settings.conf'");
          ++$upcnt;
        }
    }
  }

  /* now update things with logic */
  $hash = md5('MYVPN[add]');
  if( array_key_exists($hash, $_POST) === true && $_POST[$hash] !== '' ){
    //get largest array index in settings.conf to append the new one

    $ret = array();
    exec('grep -c "MYVPN" /pia/settings.conf', $ret);
    if( $ret[0] > 0 ){ //config must always contain an entry!
      //this ia a new failover VPN so append to end of file
      $index = $ret[0];
      $val = $_POST[$hash];
      exec("echo 'MYVPN[$index]=\'$val\'' >> '/pia/settings.conf'"); //disable forwarding
    }
  }

  if( $upcnt > 0 ){ //settings have been changed
    //refresh /etc/networking/interfaces - just in case
    $foo = array();
    exec('sudo /pia/include/network_interfaces.sh', $foo);

    $disp_body .= "<div class=\"feedback\">Settings updated</div>\n";
  }

  //how about a network restart?
  if( array_key_exists('restart_network', $_POST) && $_POST['restart_network'] === 'Full Network Restart'){
    exec('sudo /pia/include/network_restart.sh');
    $disp_body .= "<div class=\"feedback\">Network restarted</div>\n";
  }

  unset($_SESSION['settings.conf']);
  return $disp_body;
}

/**
 * function to remove a settings array from settings.conf
 * @return int number of lines removed
 */
function VPN_setting_remove_array($array_name){
  $removed = 0;

  $ret =  array();
  //get line numbers of current settings
  $config_value = substr($array_name, 0, strpos($array_name, '[') ); //this is the value of $key without [n]. this is used for the array name when writing it back
  exec('grep -n  "'.$config_value.'" /pia/settings.conf | cut -d: -f1', $ret); // $ret[] will contain line number with current settings

  //loop over returned values and remove the lines
  for( $x = count($ret)-1 ; $x >= 0 ; --$x ){ //go backwards or line numbers need to be adjusted
    exec('sed "'.$ret[$x].'d" /pia/settings.conf > /pia/settings.conf.back');
    exec('mv /pia/settings.conf.back /pia/settings.conf');
    //echo 'sed "'.$ret[$x].'d" /pia/settings.conf > /pia/settings.conf'.'<br>';
    ++$removed;
  }

  return $removed;
}


/**
 * method to update username and password passed via POST
 * @global object $_files
 * @return string string with HTML success message or empty when there was no update
 */
function update_user_settings(){
  global $_files;

  $ret = '';
  $login_file = '/pia/login.conf';
  $username = ( array_key_exists('username', $_POST) ) ? $_POST['username'] : '';
  $password = ( array_key_exists('password', $_POST) ) ? $_POST['password'] : '';

  //can not empty values right now ... but there is a reset command
  if( $username != '' ){
    if( file_exists($login_file) ){
      $c = $_files->readfile($login_file);
      $ct = explode( "\n", eol($c));
      if( $username !== $ct[0] ){
        $content = "$username\n$ct[1]"; //write new username with old password
        $_files->writefile($login_file, $content); //back to login.conf
        $ret .= "<div class=\"feedback\">Username updated</div>\n";
      }
    }
  }
  if( $password != '' ){
    if( file_exists($login_file) ){
      $c = $_files->readfile($login_file);
      $ct = explode( "\n", eol($c));
      if( $password !== $ct[1] ){
        $content = "$ct[0]\n$password"; //write old username with new password
        $_files->writefile($login_file, $content); //back to login.conf
        $ret .= "<div class=\"feedback\">Password updated</div>\n";
      }
    }
  }
  unset($_SESSION['login.conf']);
  return $ret;
}

/**
 * returns the default UI for this option
 * @return string string with HTML for body of this page
 */
function disp_vpn_default(){
  $user = VPN_get_user();

  $disp_body = '';
  /* show Username and Password fields - expand this for more VPN providers */
  $disp_body .= '<div><h2>PIA User Settings</h2>';
  $disp_body .= 'You may update your PIA username and password below.';
  $disp_body .= '<form action="/?page=config&amp;cmd=vpn_store&amp;cid=cvpn" method="post">';
  $disp_body .= '<input type="text" name="username" value="'.htmlentities($user['username']).'">';
  $disp_body .= '<input type="password" name="password" value="" placeholder="************">';
  $disp_body .= '<input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= "</form></div>";
  return $disp_body;
}

/**
 * returns the default UI for this option
 * @return string string with HTML for body of this page
 */
function disp_dhcpd_default(){
  $settings = VPN_get_settings();
  $disp_body = '';

  $disp_body .= '<div class="options_box">';
  $disp_body .= '<form action="/?page=config&amp;cmd=store_setting&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<input type="hidden" name="store" value="dhcpd_settings">';
  $disp_body .= '<h2>DHCP Server  Settings</h2>'."\n";
              $fovers = 0;
            for( $x = 0 ; $x < 10 ; ++$x ){
              if( array_key_exists('MYVPN['.$x.']', $settings) === true ){
                $ovpn = VPN_get_connections('MYVPN['.$x.']', array( 'selected' => $settings['MYVPN['.$x.']']));
                $hash = md5('MYVPN['.$x.']').'_del';
                $disp_body .= '<tr><td>Failover '.$x.'</td><td>'.$ovpn.' <input type="checkbox" name="'.$hash.'" value="1"> delete</td></tr>'."\n";
                ++$fovers;
              }
            }
            $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
            $hash = md5('NAMESERVERS[0]');
            $disp_body .= '<tr><td>DNS 1</td><td><input type="text" name="'.$hash.'" value="'.$settings['NAMESERVERS[0]'].'"></td></tr>'."\n";
            $hash = md5('NAMESERVERS[1]');
            $disp_body .= '<tr><td>DNS 2</td><td><input type="text" name="'.$hash.'" value="'.$settings['NAMESERVERS[1]'].'"></td></tr>'."\n";
            $hash = md5('NAMESERVERS[2]');
            $disp_body .= '<tr><td>DNS 3</td><td><input type="text" name="'.$hash.'" value="'.$settings['NAMESERVERS[2]'].'"></td></tr>'."\n";
            $hash = md5('NAMESERVERS[3]');
            $disp_body .= '<tr><td>DNS 4</td><td><input type="text" name="'.$hash.'" value="'.$settings['NAMESERVERS[3]'].'"></td></tr>'."\n";
            $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";


  $disp_body .= "<table>\n";
  $sel = array(
          'id' => 'IF_ETH0_DHCP_SERVER',
          'selected' => $settings['IF_ETH0_DHCP_SERVER'],
          array( 'no', 'disabled'),
          array( 'yes', 'enabled')
        );
  $disp_body .= '<tr><td>DHCP server on eth0</td><td>'.build_select($sel).'</td></tr>'."\n";

  $sel = array(
          'id' => 'IF_ETH1_DHCP_SERVER',
          'selected' => $settings['IF_ETH1_DHCP_SERVER'],
          array( 'no', 'disabled'),
          array( 'yes', 'enabled')
        );
  $disp_body .= '<tr><td>DHCP server on eth1</td><td>'.build_select($sel).'</td></tr>'."\n";

  //DHCPD network stuff
  $hash = md5('DHCPD_SUBNET');
  $disp_body .= '<tr><td>DHCPD Subnet</td><td><input type="text" name="'.$hash.'" value="'.htmlspecialchars($settings['DHCPD_SUBNET']).'"></td></tr>'."\n";

  $hash = md5('DHCPD_MASK');
  $disp_body .= '<tr><td>DHCPD Subnetmask</td><td><input type="text" name="'.$hash.'" value="'.htmlspecialchars($settings['DHCPD_MASK']).'"></td></tr>'."\n";


  $hash = md5('DHCPD_BROADCAST');
  $disp_body .= '<tr><td>DHCPD Broadcasr IP</td><td><input type="text" name="'.$hash.'" value="'.htmlspecialchars($settings['DHCPD_BROADCAST']).'"></td></tr>'."\n";

  $hash = md5('DHCPD_RANGE');
  $disp_body .= '<tr><td>DHCPD IP Range</td><td><input class="long" type="text" name="'.$hash.'" value="'.htmlspecialchars($settings['DHCPD_RANGE']).'"></td></tr>'."\n";


  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_firewall" value="Restart Firewall">';
  $disp_body .= '</div>';

  return $disp_body;
}


/**
 * returns the default UI for this option
 * @return string string with HTML for body of this page
 */
function disp_network_default(){
  $settings = VPN_get_settings();

  $disp_body = '';
  /* show Username and Password fields - expand this for more VPN providers */
  $disp_body .= '<form action="/?page=config&amp;cmd=network_store&amp;cid=cnetwork" method="post">'."\n";
  $disp_body .= '<div class="options_box">';
  $disp_body .= '<h2>PIA Network Settings</h2>'."\n";
  $disp_body .= "<table>\n";

  //basic interface and network
  $sel = array(
          'id' => 'FORWARD_PORT_ENABLED',
          'selected' =>  $settings['FORWARD_PORT_ENABLED'],
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>Enable Port Forwarding</td><td>'.build_select($sel).'</td></tr>'."\n";
  $hash = md5('FORWARD_IP');
  $disp_body .= '<tr><td>Forward IP</td><td><input type="text" name="'.$hash.'" value="'.htmlspecialchars($settings['FORWARD_IP']).'"></td></tr>'."\n";

  //VM LAN segment forwarding
  $sel = array(
            'id' => 'FORWARD_VM_LAN',
            'selected' =>  $settings['FORWARD_VM_LAN'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>VPN Gateway for VM LAN</td><td>'.build_select($sel).'</td></tr>'."\n";
  //use public LAN segment for forwarding
  $sel = array(
            'id' => 'FORWARD_PUBLIC_LAN',
            'selected' =>  $settings['FORWARD_PUBLIC_LAN'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>VPN Gateway for public LAN</td><td>'.build_select($sel).'</td></tr>'."\n";

  //management stuff
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

  //these are array settings so get them first then loop over to display them
  $use = 'FIREWALL_IF_SSH';
  $fw_ssh = VPN_get_settings_array($use);
    $c = count($fw_ssh);
    $t='';
    for( $x=0 ; $x < $c ; ++$x ){
      $hash = md5($use.'['.$x.']');
      #  $disp_body .= '<tr><td>Allow ssh logins on</td><td><input type="checkbox" name="ssh_enable_eth0" value="1"> eth0 <input type="checkbox" name="ssh_enable_eth1" value="1"> eth1</td></tr>'."\n";
      $t .= htmlspecialchars($settings[$use.'['.$x.']']).",";
    }
  $t = rtrim($t, ',');
  $disp_body .= '<tr><td>Allow ssh logins on</td><td><input type="text" name="'.$hash.'_combined" value="'.$t."\">\n".'</td></tr>'."\n";


  //now FIREWALL_IF_WEB options
  $use = 'FIREWALL_IF_WEB';
  $fw_ssh = VPN_get_settings_array($use);
    $c = count($fw_ssh);
    $t='';
    for( $x=0 ; $x < $c ; ++$x ){
      $hash = md5($use.'['.$x.']');
      #  $disp_body .= '<tr><td>Allow ssh logins on</td><td><input type="checkbox" name="ssh_enable_eth0" value="1"> eth0 <input type="checkbox" name="ssh_enable_eth1" value="1"> eth1</td></tr>'."\n";
      $t .= htmlspecialchars($settings[$use.'['.$x.']']).",";
    }
    $t = rtrim($t, ',');
    $disp_body .= '<tr><td>Allow web logins on</td><td><input type="text" name="'.$hash.'_combined" value="'.$t."\">\n".'</td></tr>'."\n";



  //$disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_firewall" value="Restart Firewall">';
  $disp_body .= '</div>';



  $disp_body .= '<div class="options_box">';
  $disp_body .= '<h2>PIA Daemon Settings</h2>'."\n";
  $disp_body .= "<table>\n";  //iptables options
  //VM LAN segment forwarding
  $sel = array(
            'id' => 'DAEMON_ENABLED',
            'selected' =>  $settings['DAEMON_ENABLED'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Enable pia-daemon</td><td>'.build_select($sel).'</td></tr>'."\n";

  //Failover connection selection - offer 10 entires
  $fovers = 0;
  for( $x = 0 ; $x < 10 ; ++$x ){
    if( array_key_exists('MYVPN['.$x.']', $settings) === true ){
      $ovpn = VPN_get_connections('MYVPN['.$x.']', array( 'selected' => $settings['MYVPN['.$x.']']));
      $hash = md5('MYVPN['.$x.']').'_del';
      $disp_body .= '<tr><td>Failover '.$x.'</td><td>'.$ovpn.' <input type="checkbox" name="'.$hash.'" value="1"> delete</td></tr>'."\n";
      ++$fovers;
    }
  }
 $ovpn = VPN_get_connections('MYVPN[add]', array('initial' => 'empty'));
 $disp_body .= '<tr><td>Add Failover</td><td>'.$ovpn.'</td></tr>'."\n";


  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings">';
  $disp_body .= '</div>';


  /* system settings */
  $disp_body .= '<div class="options_box">';
  $disp_body .= '<h2>VM System Settings</h2>'."\n";
  $disp_body .= "<table>\n";  //iptables options

  //interface assignment
  $sel = array(
          'id' => 'IF_EXT',
          'selected' =>  $settings['IF_EXT'],
          array( 'eth0', 'eth0'),
          array( 'eth1', 'eth1'),
          array( 'tun0', 'tun0')
        );
  $disp_body .= '<tr><td>Public LAN interface</td><td>'.build_select($sel).'</td></tr>'."\n";
  $sel = array(
          'id' => 'IF_INT',
          'selected' =>  $settings['IF_INT'],
          array( 'eth0', 'eth0'),
          array( 'eth1', 'eth1'),
          array( 'tun0', 'tun0')
        );
  $disp_body .= '<tr><td>VM LAN interface</td><td>'.build_select($sel).'</td></tr>'."\n";
  $sel = array(
          'id' => 'IF_TUNNEL',
          'selected' =>  $settings['IF_TUNNEL'],
          array( 'eth0', 'eth0'),
          array( 'eth1', 'eth1'),
          array( 'tun0', 'tun0')
        );
  $disp_body .= '<tr><td>VPN interface</td><td>'.build_select($sel).'</td></tr>'."\n";

  //eth0
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $disabled = ($settings['IF_ETH0_DHCP'] === 'yes') ? 'disabled' : ''; //disable input fields when DHCP is set
  $sel = array(
          'id' => 'IF_ETH0_DHCP',
          'selected' => $settings['IF_ETH0_DHCP'],
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>eth0 use DHCP</td><td>'.build_select($sel).'</td></tr>'."\n";
  $hash = md5('IF_ETH0_IP');
  $disp_body .= '<tr><td>eth1 IP</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH0_IP'].'"></td></tr>'."\n";
  $hash = md5('IF_ET0_SUB');
  $disp_body .= '<tr><td>eth1 Subnet</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH0_SUB'].'"></td></tr>'."\n";
  $hash = md5('IF_ETH0_GW');
  $disp_body .= '<tr><td>eth1 Gateway</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH0_GW'].'"></td></tr>'."\n";

  //eth1
  $disabled = ($settings['IF_ETH1_DHCP'] === 'yes') ? 'disabled' : ''; //disable input fields when DHCP is set
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $sel = array(
          'id' => 'IF_ETH1_DHCP',
          'selected' => $settings['IF_ETH1_DHCP'],
          array( 'yes', 'yes'),
          array( 'no', 'no')
        );
  $disp_body .= '<tr><td>eth1 use DHCP</td><td>'.build_select($sel).'</td></tr>'."\n";
  $hash = md5('IF_ETH1_IP');
  $disp_body .= '<tr><td>eth1 IP</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH1_IP'].'"></td></tr>'."\n";
  $hash = md5('IF_ETH1_SUB');
  $disp_body .= '<tr><td>eth1 Subnet</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH1_SUB'].'"></td></tr>'."\n";
  $hash = md5('IF_ETH1_GW');
  $disp_body .= '<tr><td>eth1 Gateway</td><td><input '.$disabled.' type="text" name="'.$hash.'" value="'.$settings['IF_ETH1_GW'].'"></td></tr>'."\n";

  //DNS
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $hash = md5('NAMESERVERS[0]');
  $disp_body .= '<tr><td>DNS 1</td><td><input type="text" name="'.$hash.'" value="'.$settings['NAMESERVERS[0]'].'"></td></tr>'."\n";
  $hash = md5('NAMESERVERS[1]');
  $disp_body .= '<tr><td>DNS 2</td><td><input type="text" name="'.$hash.'" value="'.$settings['NAMESERVERS[1]'].'"></td></tr>'."\n";
  $hash = md5('NAMESERVERS[2]');
  $disp_body .= '<tr><td>DNS 3</td><td><input type="text" name="'.$hash.'" value="'.$settings['NAMESERVERS[2]'].'"></td></tr>'."\n";
  $hash = md5('NAMESERVERS[3]');
  $disp_body .= '<tr><td>DNS 4</td><td><input type="text" name="'.$hash.'" value="'.$settings['NAMESERVERS[3]'].'"></td></tr>'."\n";
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";

  //command line stuff
  $disp_body .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>'."\n";
  $sel = array(
            'id' => 'VERBOSE',
            'selected' =>  $settings['VERBOSE'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Verbose</td><td>'.build_select($sel).'</td></tr>'."\n";
  $sel = array(
            'id' => 'VERBOSE_DEBUG',
            'selected' =>  $settings['VERBOSE_DEBUG'],
            array( 'yes', 'yes'),
            array( 'no', 'no')
          );
  $disp_body .= '<tr><td>Debug Verbose</td><td>'.build_select($sel).'</td></tr>'."\n";

  $disp_body .= "</table>\n";
  $disp_body .= '<br><input type="submit" name="store settings" value="Store Settings"> ';
  $disp_body .= ' &nbsp; <input type="submit" name="restart_network" value="Full Network Restart">';

  $disp_body .= "</form>";
  $disp_body .= '</div>';
  return $disp_body;
}

/**
 * method read /pia/login.conf into an array
 * @return array,bool array with ['name'], ['password'] OR FALSE on failure
 */
function VPN_get_user(){
  //get username and password from file or SESSION
  if( array_key_exists('login.conf', $_SESSION) !== true ){
    $ret = load_login();
    if( $ret !== false ){
      return $ret;
    }
  }
  return $_SESSION['login.conf'];
}
?>