#!/bin/bash
# script to help debug issues when port forwarding will not enable
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'
source '/pia/include/functions.sh'
RET_FORWARD_PORT="FALSE"

#function to get the port used for port forwarding
# "returns" RET_FORWARD_PORT with the port number as the value or FALSE
function get_forward_port() {
  RET_FORWARD_PORT="FALSE"

  #this only works with pia
  get_provider
  if [ "$RET_PROVIDER_NAME" = "PIAtcp" ] || [ "$RET_PROVIDER_NAME" = "PIAudp" ]; then

    #check if the client ID has been generated and get it
    if [ ! -f "/pia/client_id" ]; then
      head -n 100 /dev/urandom | md5sum | tr -d " -" > "/pia/client_id"
    fi
    PIA_CLIENT_ID=`cat /pia/client_id`
    PIA_UN=`sed -n '1p' /pia/login-pia.conf`
    PIA_PW=`sed -n '2p' /pia/login-pia.conf`

    TUN_IP=`/sbin/ip addr show $IF_TUNNEL | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`

    #get open port of tunnel connection
    TUN_PORT=`curl -ks -d "user=$PIA_UN&pass=$PIA_PW&client_id=$PIA_CLIENT_ID&local_ip=$TUN_IP" https://www.privateinternetaccess.com/vpninfo/port_forward_assignment | cut -d: -f2 | cut -d} -f1`


    #print output to aid in debugging
    echo -e "[info] cID: $PIA_CLIENT_ID UN: $PIA_UN PW: $PIA_PW"
    echo -e "[info] asking PIA for IP: $TUN_IP - returned $TUN_PORT"
    echo -e "[info] rerunning curl with error reporting enabled ...."
    curl -ksS -d "user=$PIA_UN&pass=$PIA_PW&client_id=$PIA_CLIENT_ID&local_ip=$TUN_IP" https://www.privateinternetaccess.com/vpninfo/port_forward_assignment
    echo



    #the location may not support port forwarding
    if [[ "$TUN_PORT" =~ ^[0-9]+$ ]]; then
      RET_FORWARD_PORT=$TUN_PORT
    else
      RET_FORWARD_PORT="FALSE"
    fi
  fi
}

#get open port of tunnel connection
get_forward_port
TUN_PORT=$RET_FORWARD_PORT
#the location may not support port forwarding
if [[ "$TUN_PORT" =~ ^[0-9]+$ ]]; then
	PORT_FW="enabled"
else
	PORT_FW="disabled"
fi