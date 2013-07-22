#!/bin/bash
LANG=en_US.UTF-8
export LANG

#return variables and static stuff
RET_PING_VPN=""
RET_PING_INTERNET=""
PING_COMMAND="ping -qnw2c1 -I $IF_TUNNEL 8.8.8.8 2>/dev/null | grep -c \", 0% packet loss\""
PING_COMMAND_INTERNET="ping -qnw2c1 -I $IF_EXT 8.8.8.8 2>/dev/null | grep -c \", 0% packet loss\""

#function to ping through the current VPN connection
function ping_vpn() {
  TUNNEL_PING=`eval $PING_COMMAND`
  #echo $TUNNEL_PING
  if [ "$TUNNEL_PING" = "1" ]; then
    RET_PING_VPN="OK"
	#echo "OK: $TUNNEL_PING---"
  else
    RET_PING_VPN="ERROR"
	if [ "$VERBOSE" = "yes" ]; then
		echo -e "[ info ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- VPN ping failed: "$PING_COMMAND
	fi
	#echo "Er: $TUNNEL_PING---"
  fi  
}

#function to ping through the external interface
function ping_internet() {
  EXT_PING=`eval $PING_COMMAND_INTERNET`
  #echo $PING_COMMAND_INTERNET
  if [ "$EXT_PING" = "1" ]; then
    RET_PING_INTERNET="OK"
	#echo "OK: $RET_PING_INTERNET---"
  else
    RET_PING_INTERNET="ERROR"
	#echo "Er: $RET_PING_INTERNET---"
  fi  
}

#function to handle switching of failover connections by using the MYVPN array
function switch_vpn() {
	for CONN in "${MYVPN[@]}"
	do
		if [ "$VERBOSE" = "yes" ]; then
			echo -e "[ info ] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- establishing a VPN connection to $CONN."
			echo -e "\tsee /pia/session.log for details"
			killall openvpn &> /dev/null
			openvpn "/pia/ovpn/$CONN.ovpn" &>> /pia/session.log &	
		fi

		#wait until connection has been established
		LOOP_PROTECT=0
		while true; do
			ping_vpn
			if [ "$RET_PING_VPN" = "OK" ]; then
				echo -e "[\e[1;32m ok \e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
					"- VPN connection established to $CONN"	
				/pia/pia-forward start
				return
			fi
			
			#endless loop protect, about 1ß seconds
			if [ "$LOOP_PROTECT" -eq 10 ]; then
				killall openvpn 2>/dev/null
				/pia/pia-forward stop quite
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
	echo -e "\twill try again in $SLEEP_RECONNECT_ERROR seconds." 
	sleep $SLEEP_RECONNECT_ERROR
}