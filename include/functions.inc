#!/bin/bash
LANG=en_US.UTF-8
export LANG

#return variables for functions
RET_PING=""

#function to ping through the current VPN connection
function ping_vpn() {
  TUNNEL_PING=`eval $PING_COMMAND`
  if [ "$TUNNEL_PING" -eq 1 ]; then
    RET_PING="OK"
  else
    RET_PING="ERROR"
  fi  
}

#function to handle switching of failover connections by using the MYVPN array
function switch_vpn() {
	for CONN in "${MYVPN[@]}"
	do
		if [ "$VERBOSE" = "yes" ]; then
			echo -e "[ info ] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- establishing a VPN connection to $CONN. see /pia/session.log for details"
			killall openvpn &> /dev/null
			openvpn "/pia/ovpn/$CONN.ovpn" &>> /pia/session.log &	
		fi

		#wait until connection has been established
		LOOP_PROTECT=0
		while true; do
			ping_vpn
			if [ "$RET_PING" = "OK" ]; then
				echo "ping OK - return"
				return
			fi
			
			#endless loop protect, about 1ß seconds
			if [ "$LOOP_PROTECT" -eq 10 ]; then
				echo -e "[\e[1;31m error \e[0m] unable to connect to $CONN. will try next target"
				killall openvpn 2>/dev/null
				/pia/pia-forward stop quite
				
				echo "over loop - break"
				break
			else
				sleep 1
				LOOP_PROTECT=$(($LOOP_PROTECT + 1))
			fi		
		done

		echo -e "[\e[1;33m warning \e[0m] tried to reconnect to $CONN but the connection failed."
	done
 
 
	echo -e "[\e[1;31m error \e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
		"- unable to connect to any backup connection."
	echo -e "\twill try again in 5 minutes." 
	sleep 320
}