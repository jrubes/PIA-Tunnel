#!/bin/bash
LANG=en_US.UTF-8
export LANG

#return variables and static stuff
RET_PING_HOST=""
declare -a RET_MODIFIED_ARRAY
RET_FORWARD_PORT="FALSE"
PING_COMMAND="ping -qn -i 0.5 -w 4 -W 0.5 -I INTERFACE IP2TOPING 2>/dev/null | grep -c \", 0% packet loss\""

# fallback list
PING_IP_LIST[0]="8.8.8.8"
PING_IP_LIST[1]="8.8.4.4"
PING_IP_LIST[2]="208.67.222.222"
PING_IP_LIST[3]="208.67.220.220"

VERBOSE_DEBUG="NO" #yes/no


# checks if $1 is found in the PING_IP_LIST array
# $2 may contain a custom array with IPs
# sets $RET_IP_UNIQUE to "yes" if unique
function is_ip_unique() {

  if [ "$1" = "" ]; then
    RET_IP_UNIQUE="no"
    #echo "debug - not unique $1"
    return
  fi
  
  if [ "$2" = "" ]; then
    ping_array=("${PING_IP_LIST[@]}")
	#echo "using PING_IP_LIST"
  else
    declare -a ping_array=("${!2}")
  fi

  #loop over array and break once a match has been found
  for ip_unique in ${ping_array[@]}
  do
    #echo "debug - testing $ip_unique vs $1"
    if [ "$ip_unique" = "$1" ]; then
      RET_IP_UNIQUE="no"
      #echo "debug - not unique $1"
      return
    fi
  
  done
  
  #echo "debug - unique $1"
  RET_IP_UNIQUE="yes"
}

#function to grab a few IPs from /pia/include/ip_list.txt
# and store them for later in $PING_eIP_LIST[]
function gen_ip_list() {

	if [ ! -f "/pia/include/ip_list.txt" ]; then
		echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
		  "- \"/pia/include/ip_list.txt\" does not exist. Please run pia-setup first"
		exit
	fi

  if [ ! "$1" = "" ]; then
    THIS_MANY=$1
  else
    THIS_MANY=10
  fi
  if [ "$VERBOSE_DEBUG" = "yes" ]; then
	echo -e "[ debug ] "$(date +"%Y-%m-%d %H:%M:%S")\
		"- generating $THIS_MANY fresh IPs"
		
  fi
  
  #read list of IPs into IP_LIST array
  IFS=$'\r\n' IP_LIST=($(cat "/pia/include/ip_list.txt" | tail -n+2))
  #get array length
  IP_COUNT=${#IP_LIST[@]}
  IP_COUNT=$((IP_COUNT - 1)) #zero based

  #get THIS_MANY random numbers
  PING_INDEX=0
  LOOP_PROTECT=0
  while true; do
  #for (( x=0 ; x < $THIS_MANY ; x++ ))
    AINDEX=$[ ( $RANDOM % $IP_COUNT )  + 1 ]
    
    #have indexes now, get the IP for it
    is_ip_unique ${IP_LIST[$AINDEX]}
    if [ "$RET_IP_UNIQUE" = "yes" ]; then
      #IP is unqieu store it!
      PING_IP_LIST[$PING_INDEX]=${IP_LIST[$AINDEX]}
      PING_INDEX=$((PING_INDEX + 1))
    fi
    
    if [ "$PING_INDEX" = "$THIS_MANY" ]; then
      break # collected enough IPs
    fi
    
    #endless loop protect,
    if [ "$LOOP_PROTECT" -eq 5000 ]; then
      echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
	      "- unable to select the requestd number of IPs ($THIS_MANY) - tried $LOOP_PROTECT times"
      break
    else
      LOOP_PROTECT=$((LOOP_PROTECT + 1))
    fi
  done
  
  # final check
  if [ "${#PING_IP_LIST[@]}" = "0" ]; then
      echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
	      "- FATAL - PING_IP_LIST is empty. must terminate!"
      exit
  fi
  
  # $PING_IP_LIST[] now contains a few IPs which may 
  # be used by other functions  
}

# new ping function for pinging internet hosts
# $s1 is either "internet" or "vpn"
# $s2 the IP or Domain to ping
function ping_host() {
	RET_PING_HOST="ERROR"

	if [ "$2" != "" ]; then
		host_ip="$2"
	else
		#check PING_IP_LIST and ensure that it still has enough IPs since
		# failed IPs get removed from the array further down
		if [ ${#PING_IP_LIST[@]} -lt 2 ];then
			if [ "$VERBOSE_DEBUG" = "yes" ]; then
				echo -e "[ debug ] "$(date +"%Y-%m-%d %H:%M:%S")\
					"- PING_IP_LIST only has 1 entry left - rebuilding"
			fi
			gen_ip_list 15
		fi
		
		#pick one IP from $PING_IP_LIST[] to be used this time
		ip_count=${#PING_IP_LIST[@]}
		ip_count=$((ip_count - 1)) #zero based
		rand=$[ ( $RANDOM % $ip_count )  + 1 ]
		host_ip=${PING_IP_LIST[$rand]}
	fi
  
    
  if [ "$1" = "internet" ]; then
    #replace IP in $PING_COMMAND with $host_ip
    pingthis=`echo "$PING_COMMAND" | sed -e "s/IP2TOPING/$host_ip/g"`
    pingthis=`echo "$pingthis" | sed -e "s/INTERFACE/$IF_EXT/g"`
    PING_RESULT=`eval $pingthis`
  else
    pingthis=`echo "$PING_COMMAND" | sed -e "s/IP2TOPING/$host_ip/g"`
    pingthis=`echo "$pingthis" | sed -e "s/INTERFACE/$IF_TUNNEL/g"`
    PING_RESULT=`eval $pingthis`
  fi

  if [ "$PING_RESULT" = "1" ]; then
    RET_PING_HOST="OK"

  else
    RET_PING_HOST="ERROR"
    if [ "$VERBOSE_DEBUG" = "yes" ]; then
		if [ "$1" = "internet" ]; then
			echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- Internet ping failed $host_ip!"
		else
			echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- VPN ping failed $host_ip!"
		fi
    fi

	
	#ping failed remove the IP from the random ping pool PING_IP_LIST
	remove_ip_from_array PING_IP_LIST[@] "$host_ip"
	PING_IP_LIST=("${RET_MODIFIED_ARRAY[@]}")
  fi      

}

#function to remove an item from any array and rebuilding the array without the item
# keys will not be preserved
function remove_ip_from_array() {
  unset RET_MODIFIED_ARRAY #clear contents
  
  if [ "$1" = "" ]; then
	echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
	  "- FATAL SCRIPT ERROR, an array is required here!"
	return
  else
    declare -a source_array=("${!1}")
  fi

  if [ "$2" = "" ]; then
    return
  fi
  
  new_count=0
  for item in ${source_array[@]}
  do
	if [ "$item" != "$2" ]; then
		RET_MODIFIED_ARRAY[$new_count]="$item"
		new_count=$((new_count + 1))
	else
		if [ "$VERBOSE_DEBUG" = "yes" ]; then
			echo -e "[ debug ] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- removed $2 from IP array"
			
		fi
	fi
  done
  
  #RET_MODIFIED_ARRAY is now the new array
}


#function to handle switching of failover connections by using the MYVPN array
function switch_vpn() {
	for CONN in "${MYVPN[@]}"
	do
		if [ "$VERBOSE" = "yes" ]; then
			echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- establishing a VPN connection to $CONN."
			echo -e "\tsee /pia/session.log for details"
		fi
		killall openvpn &> /dev/null
		openvpn "/pia/ovpn/$CONN.ovpn" &>> /pia/session.log &	

		#wait until connection has been established
		LOOP_PROTECT=0
		while true; do
			ping_host "vpn"
			if [ "$RET_PING_HOST" = "OK" ]; then
					
				# get port forwarding info
				get_forward_port
				if [ "$RET_FORWARD_PORT" != "FALSE" ]; then
				  TUN_IP=`/sbin/ip addr show $IF_TUNNEL | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`
				  fw_port_forward="\n[info] "$(date +"%Y-%m-%d %H:%M:%S")" - IP: $TUN_IP Port: $RET_FORWARD_PORT"
				else
				  fw_port_forward=""
				fi
				
				# show connection data
				echo -e "[\e[1;32m ok \e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
					"- VPN connection to $CONN established"$fw_port_forward
				/pia/pia-forward start quite
				RAN_FORWARD_FIX="no" #reset on working connection
				return
			fi
			
			#endless loop protect, about 40 seconds
			if [ "$LOOP_PROTECT" -eq 20 ]; then
				killall openvpn 2>/dev/null
				/pia/pia-forward stop quite
				break
			else
				sleep 2
				LOOP_PROTECT=$((LOOP_PROTECT + 1))
			fi		
		done

		echo -e "[\e[1;33mwarn\e[0m] tried to reconnect to $CONN but the connection failed."
	done
 
 
	echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
		"- unable to connect to any backup connection."
	echo -e "\twill try again in $SLEEP_RECONNECT_ERROR seconds." 
	sleep $SLEEP_RECONNECT_ERROR
}


#function to get the port used for port forwarding
# "returns" RET_FORWARD_PORT with the port number as the value or FALSE
function get_forward_port() {
  RET_FORWARD_PORT="FALSE"
  
  #check if the client ID has been generated and get it
  if [ ! -f "/pia/client_id" ]; then
    head -n 100 /dev/urandom | md5sum | tr -d " -" > "/pia/client_id"
  fi
  PIA_CLIENT_ID=`cat /pia/client_id`
  PIA_UN=`sed -n '1p' /pia/login.conf`
  PIA_PW=`sed -n '2p' /pia/login.conf`
  TUN_IP=`/sbin/ip addr show $IF_TUNNEL | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`

  #get open port of tunnel connection
  TUN_PORT=`curl -ks -d "user=$PIA_UN&pass=$PIA_PW&client_id=$PIA_CLIENT_ID&local_ip=$TUN_IP" https://www.privateinternetaccess.com/vpninfo/port_forward_assignment | cut -d: -f2 | cut -d} -f1`

  #the location may not support port forwarding
  if [[ "$TUN_PORT" =~ ^[0-9]+$ ]]; then
    RET_FORWARD_PORT=$TUN_PORT
  else
    RET_FORWARD_PORT="FALSE"
  fi
}

# checks if the internet is up by pining, will restart networking once if the internet is down
# the restart will fix the routing table and is hopefully only required once. needs testing :)
function check_repair_internet() {
	RAN_FORWARD_FIX="no"
	LOOP_TIMEOUT=1
	while true; do
		
		ping_host "internet"
		if [ "$RET_PING_HOST" = "OK" ]; then
			#internet works, keep going
			if [ "$VERBOSE" = "yes" ]; then
				echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
					"- Internet is back up after $LOOP_TIMEOUT of 5 attempts"
			fi
			break;
			
		else
			if [ "$RAN_FORWARD_FIX" = "no" ]; then
				#only do this once per internet connection failure
				echo -e "[\e[1;33mwarn\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
					"- Internet connection appears to be down"
				echo -e "\trunning pia-forward fix"
				RAN_FORWARD_FIX="yes"
				/pia/pia-forward fix quite
			fi
		fi
		

		#ping loop timeout
		if [ "$LOOP_TIMEOUT" -gt $FAIL_RETRY_INTERNET ]; then
			echo -e "[\e[1;33mwarn\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
				"forwarding disabled until the VPN is back up."
			echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- Internet is DOWN! Recheck in $SLEEP_INTERNET_DOWN seconds"
			/pia/pia-forward stop quite
			exit			
		else
			if [ "$VERBOSE" = "yes" ]; then
				echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
					"- Internet failure $LOOP_TIMEOUT of $FAIL_RETRY_INTERNET"
			fi
			sleep $SLEEP_PING_RETEST
			LOOP_TIMEOUT=$(($LOOP_TIMEOUT + 1))
		fi	
	done
}