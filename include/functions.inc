#!/bin/bash
LANG=en_US.UTF-8
export LANG

#return variables fro functions
RET_PING=""
#function to ping through the current VPN connection
function ping_vpn () {
  TUNNEL_PING=`eval $PING_COMMAND`
  if [ "$TUNNEL_PING" -eq 1 ]; then
    RET_PING="OK"
  else
    RET_PING="ERROR"
  fi  
}

#function to handle switching of failover connections by using the MYVPN array
function switch_vpn() {
  for conn in "${MYVPN[@]}"
  do
    # create a new VPN connection
    /pia/pia-start \""$conn"\"
    
    # now do the ping check
    ping_vpn
    if [ "$RET_PING" = "OK" ]; then
      break
    else
      echo "tried to reconnect to $conn but the connection failed."
    fi
  done
  
  echo "unable to switch connections because none appears to work. will try again in 5 minutes"
  sleep 320
}