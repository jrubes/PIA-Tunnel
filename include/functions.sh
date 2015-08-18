#!/bin/bash
LANG=en_US.UTF-8
export LANG

#return variables and static stuff
RET_PING_HOST=""
RET_FILE_IS_WRITABLE="no"
declare -a RET_MODIFIED_ARRAY
RET_FORWARD_PORT="FALSE"
RET_FORWARD_STATE="FUCK"
RET_GET_PACKET_LOSS=""

# WARNING DO NOT CHANGE the ping command! ping_host uses sed to modify the string
PING_VER=`ping -V > /dev/null 2>&1`
if [ $? -eq 0 ]; then
	#Debian
	PING_COMMAND="ping -qn -i 0.5 -w 4 -W 0.5 -I INTERFACE IP2TOPING 2>/dev/null | grep -c \", 0% packet loss\""
	#PING_PACKET_LOSS="ping -qn -i 0.5 -w 4 -W 0.5 -I INTERFACE IP2TOPING 2>/dev/null | grep \"packet loss\" | gawk -F\",\" '{print \$3}' | gawk -F \"%\" '{print \$1}' | tr -d ' '"
	PING_PACKET_LOSS="ping -qn -i 0.5 -w 4 -W 0.5 -I INTERFACE IP2TOPING 2>/dev/null | grep \"packet loss\""
else
	#FreeBSD
        PING_COMMAND="ping -qn -i 0.5 -t 4 -W 0.5 IP2TOPING 2>/dev/null | /usr/bin/grep -c \", 0% packet loss\""
        PING_PACKET_LOSS="ping -qn -i 0.5 -t 4 -W 0.5 IP2TOPING 2>/dev/null | /usr/bin/grep \"packet loss\""
fi

# fallback list
PING_IP_LIST[0]="8.8.8.8"
PING_IP_LIST[1]="8.8.4.4"
PING_IP_LIST[2]="208.67.222.222"
PING_IP_LIST[3]="208.67.220.220"


# checks if at least one of the login files has been filled
function check_default_username(){
	#check if login files exist
    FCOUNT=`ls -1 /usr/local/pia/login-*.conf 2>/dev/null | wc -l`

    if [ "$FCOUNT" -lt 1 ]; then
        # FATAL ERROR: make sure to always print IP info
        INT_IP=`/sbin/ip addr show $IF_INT | /usr/bin/grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`
        EXT_IP=`/sbin/ip addr show $IF_EXT | /usr/bin/grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`
        echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")" - Public LAN IP: $EXT_IP"
        echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")" - Private LAN IP: $INT_IP"

        echo
		echo
		echo "No VPN account info found! Please use the WebUI to configure "
        echo "your account or add the information manually to one of the following files."
        echo
		echo -e "\t/usr/local/pia/login-pia.conf"
		echo -e "\t/usr/local/pia/login-FrootVPN.conf"
        echo
		echo "Try"
		echo -e "\tvi /usr/local/pia/login-pia.conf"
		echo -e "\tvi /usr/local/pia/login-FrootVPN.conf"
		echo "or"
		echo -e "\tnano /usr/local/pia/login-pia.conf"
		echo -e "\tnano /usr/local/pia/login-FrootVPN.conf"
		echo
		exit
	fi
}


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

#function to grab a few IPs from /usr/local/pia/ip_list.txt
# and store them for later in $PING_eIP_LIST[]
function gen_ip_list() {

	if [ ! -f "/usr/local/pia/ip_list.txt" ]; then
		echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
		  "- \"/usr/local/pia/ip_list.txt\" does not exist. Please run pia-setup first"
		exit
	fi

  if [ ! "$1" = "" ]; then
    THIS_MANY=$1
  else
    THIS_MANY=10
  fi
  if [ "$VERBOSE_DEBUG" = "yes" ]; then
	echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
		"- generating $THIS_MANY fresh IPs"

  fi

  #read list of IPs into IP_LIST array
  IFS=$'\r\n' IP_LIST=($(cat "/usr/local/pia/ip_list.txt" | tail -n+2))
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
# $s1 is either "internet", "vpn" or "any"
#  "any" means either one or both, let the function figure it out
# $2 the IP or Domain to ping
# $3 or $4 "quick" for a fast ping or "keep" to keep the IP in the IP cache
function ping_host() {
	RET_PING_HOST="ERROR"

	host_ip="not set"
	if [ "$2" != "" ] || [ "$3" != "" ]; then
		if [ "$2" != "quick" ] && [ "$2" != "keep" ]; then
			host_ip="$2"
		elif [ "$2" = "quick" ] && [ "$3" != "keep" ] && [ "$3" != "" ]; then
			host_ip="$3"
		fi
	fi

	if [ "$host_ip" = "not set" ]; then
		#check PING_IP_LIST and ensure that it still has enough IPs since
		# failed IPs get removed from the array further down
		if [ ${#PING_IP_LIST[@]} -lt 2 ];then
			if [ "$VERBOSE_DEBUG" = "yes" ]; then
				echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
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


	#shall we make this a "quick" ping?
	if [ "$2" = "quick" ] || [ "$3" = "quick" ]; then
		pingthis=`echo "$PING_COMMAND" | sed -e "s/-i 0.5 -w 4 -W 0.5/-c 1 -w 1/g"`
		if [ "$VERBOSE_DEBUG" = "yes" ]; then
			echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- using \"quick\" ping command"
		fi
	else
		pingthis=$PING_COMMAND
	fi


  if [ "$1" = "internet" ]; then
    #replace IP in $PING_COMMAND with $host_ip
    pingthis=`echo "$pingthis" | sed -e "s/IP2TOPING/$host_ip/g"`
    pingthis=`echo "$pingthis" | sed -e "s/INTERFACE/$IF_EXT/g"`
	PING_RESULT=`eval $pingthis`
	#echo "$pingthis"

  elif [ "$1" = "vpn" ]; then
    pingthis=`echo "$pingthis" | sed -e "s/IP2TOPING/$host_ip/g"`
    pingthis=`echo "$pingthis" | sed -e "s/INTERFACE/$IF_TUNNEL/g"`
    PING_RESULT=`eval $pingthis`
	#echo "$pingthis"

  else
	#use self to ping via VPN first then via Internet
	# return "OK" on first success or "ERROR" on complete failure
	ping_host "vpn" "$host_ip" "keep"
	if [ "$RET_PING_HOST" = "OK" ]; then
		RET_PING_HOST="OK" #ping via VPN was good
		return
	else
		ping_host "internet" "$host_ip" "keep"
		if [ "$RET_PING_HOST" = "OK" ]; then
			RET_PING_HOST="OK" #ping via Internet was good
			return
		else
			RET_PING_HOST="ERROR" #VPN and Internet are down
			return
		fi
	fi
  fi

  if [ "$PING_RESULT" = "1" ]; then
    RET_PING_HOST="OK"

  else
    RET_PING_HOST="ERROR"
    if [ "$VERBOSE_DEBUG" = "yes" ]; then
		if [ "$1" = "internet" ]; then
			echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- Internet ping failed $host_ip"
		else
			echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- VPN ping failed $host_ip"
		fi
    fi


	if [ "$2" != "keep" ] && [ "$3" != "keep" ] && [ "$4" != "keep" ]; then
	  #ping failed remove the IP from the random ping pool PING_IP_LIST
	  remove_ip_from_array PING_IP_LIST[@] "$host_ip"
	  PING_IP_LIST=("${RET_MODIFIED_ARRAY[@]}")
	fi
  fi

}

# checks if any rules are active in the FORWARD chain
# $s1 is optional and may be used to specify a single interface
function check_forward_state(){
    unset RET_FORWARD_STATE

    if [ "${1}" = "" ]; then
      ret=`iptables -nL FORWARD | /usr/bin/grep -c "ACCEPT"`
    else
      ret=`iptables -vnL FORWARD | /usr/bin/grep "ACCEPT" | /usr/bin/grep -c "${1}"`
    fi


    if [ $ret = 0 ]; then
        RET_FORWARD_STATE="OFF"
    else
        RET_FORWARD_STATE="ON"
    fi

    return
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
			echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- removed $2 from IP array"

		fi
	fi
  done

  #RET_MODIFIED_ARRAY is now the new array
}

# will print out details about VPN connection using status cache
# $1 connection name
function echo_conn_established() {
  # show connection data
  maintain_status_cache '/usr/local/pia/cache/status.txt'
  vpn_port=`cat "/usr/local/pia/cache/status.txt" | /usr/bin/grep "VPNPORT" | gawk -F":" '{print $2}'`
  #vpn_ip=`cat "/usr/local/pia/cache/status.txt" | /usr/bin/grep "VPNIP" | gawk -F":" '{print $2}'`
  vpn_ip=`/sbin/ip addr show $IF_TUNNEL | /usr/bin/grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`
  echo -e "[\e[1;32m ok \e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
	  "- VPN connection to $1 established\n\tVPN IP: $vpn_ip Port: $vpn_port"
}

#function to handle switching of failover connections by using the MYVPN array
function switch_vpn() {
	for CONN in "${MYVPN[@]}"
	do
		if [ "$VERBOSE" = "yes" ]; then
			echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- establishing a VPN connection to $CONN."
			echo -e "\tsee ${bold}/usr/local/pia/cache/session.log${normal} for details"
		fi
		killall openvpn &> /dev/null
        echo $(date +"%a %b %d %H:%M:%S %Y")" connecting to $CONN" > /usr/local/pia/cache/session.log

        #get the provider / directory name
        VPNprovider=`echo "$CONN" | gawk -F"/" '{print $1}'`

        #start openVPN session
        if [ -f "/usr/local/pia/ovpn/$CONN.ovpn" ]; then
          echo "$VPNprovider" > /usr/local/pia/cache/provider.txt
          openvpn "/usr/local/pia/ovpn/$CONN.ovpn" &>> /usr/local/pia/cache/session.log &
        else
          echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
              "- specified file not found in switch_vpn() - /usr/local/pia/ovpn/$CONN.ovpn"
          echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
              "- terminating...."
          exit 1
        fi


		#wait until connection has been established
		LOOP_PROTECT=0
		while true; do
			ping_host "vpn" "keep" #keep here since the ping will
					       #fail until the connection stands
			if [ "$RET_PING_HOST" = "OK" ]; then

				# show connection data
				echo_conn_established $CONN
				#start firewall and enable forwarding
				/usr/local/pia/pia-forward start quite
				RAN_FORWARD_FIX="no" #reset on working connection
                rm "/usr/local/pia/cache/status.txt"
				return
			fi

			#endless loop protect, about 40 seconds
			if [ "$LOOP_PROTECT" -eq 20 ]; then
				killall openvpn 2>/dev/null
				/usr/local/pia/pia-forward stop quite
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


# retrieve provider from cache file
# "returns" RET_PROVIDER_NAME with the provider string "PICtcp", "PIAudp" ...
#   it is the name of the directory inside /usr/local/pia/ovpn/
function get_provider(){
  if [ -f "/usr/local/pia/cache/provider.txt" ];then
    RET_PROVIDER_NAME=`cat /usr/local/pia/cache/provider.txt`
  else
    RET_PROVIDER_NAME=""
  fi
}


#function to get the port used for port forwarding
# "returns" RET_FORWARD_PORT with the port number as the value or FALSE
function get_forward_port() {
  RET_FORWARD_PORT="FALSE"

  #this only works with pia
  get_provider
  if [ "$RET_PROVIDER_NAME" = "PIAtcp" ] || [ "$RET_PROVIDER_NAME" = "PIAudp" ]; then

    #check if the client ID has been generated and get it
    if [ ! -f "/usr/local/pia/client_id" ]; then
      head -n 100 /dev/urandom | md5sum | tr -d " -" > "/usr/local/pia/client_id"
    fi
    PIA_CLIENT_ID=`cat /usr/local/pia/client_id`
    PIA_UN=`sed -n '1p' /usr/local/pia/login-pia.conf`
    PIA_PW=`sed -n '2p' /usr/local/pia/login-pia.conf`
    TUN_IP=`/sbin/ip addr show $IF_TUNNEL | /usr/bin/grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`

    #get open port of tunnel connection
    TUN_PORT=`curl -ks -d "user=$PIA_UN&pass=$PIA_PW&client_id=$PIA_CLIENT_ID&local_ip=$TUN_IP" https://www.privateinternetaccess.com/vpninfo/port_forward_assignment | cut -d: -f2 | cut -d} -f1`

    #the location may not support port forwarding
    if [[ "$TUN_PORT" =~ ^[0-9]+$ ]]; then
      RET_FORWARD_PORT=$TUN_PORT
    else
      RET_FORWARD_PORT="FALSE"
    fi

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
				echo -e "\trunning ${bold}pia-forward fix${normal}"
				RAN_FORWARD_FIX="yes"
				/usr/local/pia/pia-forward fix quite
			fi
		fi


		#ping loop timeout
		if [ "$LOOP_TIMEOUT" -gt $FAIL_RETRY_INTERNET ]; then
			echo -e "[\e[1;33mwarn\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- forwarding disabled until the VPN is back up."
			echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- Internet is DOWN! Recheck in $SLEEP_INTERNET_DOWN seconds"
            rm -f "/usr/local/pia/cache/session.log" &> /dev/null
			/usr/local/pia/pia-forward stop quite
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

#checks if a file exists and is writeable or if the file can be created
# $1 is the file name
# returns RET_FILE_IS_WRITABLE either "yes" or "no"
function file_is_writable() {
  file_rw_permission_check="$1"

  #check if the cache is writable. no point in running if it is not
  if [ -f "$file_rw_permission_check" ]; then
    if [ ! -w "$file_rw_permission_check" ]; then
      RET_FILE_IS_WRITABLE="no"
      return
    else
      RET_FILE_IS_WRITABLE="yes"
      return
    fi
  else
    touch "$file_rw_permission_check" 2> /dev/null
    #check exit status
    if [ "$?" = "1" ]; then
      RET_FILE_IS_WRITABLE="no"
      return
    fi
    #remove the empty file so aborting the process will not keep an empty file around
    rm -f "$file_rw_permission_check" 2> /dev/null
	RET_FILE_IS_WRITABLE="yes"
	return
  fi
}

#run an age check on a cache file
# s1 is the filename
# s2 how many minutes ago as integer, defaults to 30
# returns RET_CACHE_AGE_CHECK "OK", "EXPIRED", "NOT FOUND"
function cache_age_check() {
	cache_age_check_time_passed=30
	if [ "$2" != "" ]; then
		cache_age_check_time_passed="$2"
	fi

	if [ -f "$1" ]; then
		date_string=`head -n1 $1 | gawk -F" " '{print $4" "$5}'`
		#convert date into timestamp
		cache_ts=`date -d "$date_string" "+%s"`
		cache_ts_expired=`date -d "$cache_age_check_time_passed minutes ago" "+%s"`
		if [ $cache_ts -gt $cache_ts_expired ]; then
			RET_CACHE_AGE_CHECK="OK"
			return
		else
			RET_CACHE_AGE_CHECK="EXPIRED"
			return
		fi
	else
		RET_CACHE_AGE_CHECK="NOT FOUND"
		return
	fi
}

function maintain_status_cache() {
  maint_cache_file="$1"

  #check if the cache is writable. no point in running if it is not
  file_is_writable "$maint_cache_file"
  if [ "$RET_FILE_IS_WRITABLE" = "no" ]; then
      echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
		"- $maint_cache_file is not writable?!? $RET_FILE_IS_WRITABLE"
      exit
  fi

  #check the age of the cache file
  cache_age_check "$maint_cache_file"
  if [ "$RET_CACHE_AGE_CHECK" = "OK" ]; then
	return
  fi

  # rebuilding cache from here on #
  if [ "$VERBOSE_DEBUG" = "yes" ]; then
	  echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
		  "- rebuilding cache $maint_cache_file"
  fi

  #get default gateway of tunnel interface using "ip"
  #get IP of tunnel Gateway
  TUN_GATEWAY=`/sbin/ip route show | /usr/bin/grep "0.0.0.0/1" | /usr/local/bin/gawk -F" " '{print $3}'`
  #get IPs of interfaces
  INT_IP=`/sbin/ip addr show $IF_INT | /usr/bin/grep -w "inet" | /usr/local/bin/gawk -F" " '{print $2}' | /usr/bin/cut -d/ -f1`
  EXT_IP=`/sbin/ip addr show $IF_EXT | /usr/bin/grep -w "inet" | /usr/local/bin/gawk -F" " '{print $2}' | /usr/bin/cut -d/ -f1`

  interface_exists "$IF_TUNNEL"
  if [ "$RET_INTERFACE_EXISTS" = "yes" ]; then
    TUN_IP=`/sbin/ip addr show $IF_TUNNEL | /usr/bin/grep -w "inet" | /usr/local/bin/gawk -F" " '{print $2}' | /usr/bin/cut -d/ -f1`
  else
    TUN_IP=""
  fi

  #this only works with pia
  get_provider
  if [ "$RET_PROVIDER_NAME" = "PIAtcp" ] || [ "$RET_PROVIDER_NAME" = "PIAudp" ]; then

    #get PIA username and password from /usr/local/pia/login-pia.conf
    PIA_UN=`sed -n '1p' /usr/local/pia/login-pia.conf`
    PIA_PW=`sed -n '2p' /usr/local/pia/login-pia.conf`


    #check if the client ID has been generated and get it
    if [ ! -f /usr/local/pia/client_id ]; then
      head -n 100 /dev/urandom | md5sum | tr -d " -" > /usr/local/pia/client_id
    fi
    PIA_CLIENT_ID=`cat /usr/local/pia/client_id`


    #get open port of tunnel connection
    TUN_PORT=`curl -ks -d "user=$PIA_UN&pass=$PIA_PW&client_id=$PIA_CLIENT_ID&local_ip=$TUN_IP" https://www.privateinternetaccess.com/vpninfo/port_forward_assignment | cut -d: -f2 | cut -d} -f1`

    #the location may not support port forwarding
    if [[ "$TUN_PORT" =~ ^[0-9]+$ ]]; then
        PORT_FW="enabled"
    else
        PORT_FW="disabled"
    fi
  else
    PORT_FW="disabled"
  fi

  #write info to cache file
  echo "# generated on "$(date +"%Y-%m-%d %H:%M:%S") > "$maint_cache_file"
  echo "VPNIP:$TUN_IP" >> "$maint_cache_file"
  if [ "$PORT_FW" = "enabled" ]; then
    echo "VPNPORT:$TUN_PORT" >> "$maint_cache_file"
  else
    echo "VPNPORT:no support" >> "$maint_cache_file"
  fi
  echo "INTIP:$INT_IP" >> "$maint_cache_file"
  echo "INTERNETIP:$EXT_IP" >> "$maint_cache_file"
}


# function to check if a network interface exists
# $1 name of network interface as string
# returns RET_INTERFACE_EXISTS "yes" or "no"
function interface_exists() {
  if [ -z ${1} ]; then
    RET_INTERFACE_EXISTS="no"
    return
  fi


  #check=`ip addr show $1 2>&1 | /usr/bin/grep -c "does not exist"`
  check=`ifconfig $1 >/dev/null 2>&1`
  #if [ "$check" = "0" ]; then
  if [ $? -eq 0 ]; then
    RET_INTERFACE_EXISTS="yes"
    return
  else
    RET_INTERFACE_EXISTS="no"
    return
  fi
}

# new ping function for pinging internet hosts
# $s1 is either "internet", "vpn" or "any"
#  "any" means either one or both, let the function figure it out
# $2 the IP or Domain to ping
# $3 or $4 "quick" for a fast ping or "keep" to keep the IP in the IP cache
function ping_host_new() {
	RET_PING_HOST="ERROR"

	host_ip="not set"
	if [ "$2" != "" ] || [ "$3" != "" ]; then
		if [ "$2" != "quick" ] && [ "$2" != "keep" ]; then
			host_ip="$2"
		elif [ "$2" = "quick" ] && [ "$3" != "keep" ] && [ "$3" != "" ]; then
			host_ip="$3"
		fi
	fi

	if [ "$host_ip" = "not set" ]; then
		#check PING_IP_LIST and ensure that it still has enough IPs since
		# failed IPs get removed from the array further down
		if [ ${#PING_IP_LIST[@]} -lt 2 ];then
			if [ "$VERBOSE_DEBUG" = "yes" ]; then
				echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
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


	#shall we make this a "quick" ping?
	if [ "$2" = "quick" ] || [ "$3" = "quick" ]; then
		pingthis=`echo "$PING_PACKET_LOSS" | sed -e "s/-i 0.5 -w 4 -W 0.5/-c 1 -w 1/g"`

		if [ "$VERBOSE_DEBUG" = "yes" ]; then
			echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
				"- using \"quick\" ping command"
		fi
	else
		pingthis=$PING_PACKET_LOSS
	fi

  if [ "$1" = "internet" ]; then
    #replace IP in $PING_COMMAND with $host_ip
    pingthis=`echo "$pingthis" | sed -e "s/IP2TOPING/$host_ip/g"`
    pingthis=`echo "$pingthis" | sed -e "s/INTERFACE/$IF_EXT/g"`
    PING_RESULT=`eval $pingthis`
    #echo "$pingthis"
    #echo "$PING_RESULT"
    #exit

  elif [ "$1" = "vpn" ]; then
    pingthis=`echo "$pingthis" | sed -e "s/IP2TOPING/$host_ip/g"`
    pingthis=`echo "$pingthis" | sed -e "s/INTERFACE/$IF_TUNNEL/g"`
    PING_RESULT=`eval $pingthis`
    #echo "$pingthis"
    #echo "$PING_RESULT"
    #exit

  else
    # handle "any" ping by calling itself with either "vpn" or "internet"
    interface_exists "$IF_TUNNEL"
    if [ "$RET_INTERFACE_EXISTS" = "yes" ]; then
        ping_host_new "vpn" "$host_ip" "keep"
        return
    else
        ping_host_new "internet" "$host_ip" "keep"
        return
    fi
  fi

  #retrieve return of ping
  get_packet_loss "$PING_RESULT"
  PING_RESULT="$RET_GET_PACKET_LOSS"

  #Debug
  #echo "$PING_RESULT"
  #exit

  # see if the ping failed or is OK
  if [ "$PING_RESULT" = "" ]; then
    RET_PING_HOST="ERROR"
    if [ "$VERBOSE_DEBUG" = "yes" ]; then
        if [ "$1" = "internet" ]; then
                echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
                        "- Internet ping failed $host_ip"
        else
                echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
                        "- VPN ping failed $host_ip"
        fi
    fi
    return
  fi

  #if [ "$PING_RESULT" = "1" ]; then
  if [ "$PING_RESULT" -lt "$PING_MAX_LOSS" ]; then
    RET_PING_HOST="OK"
    if [ "$VERBOSE_DEBUG" = "yes" ]; then
        if [ "$1" = "internet" ]; then
            echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
                "- Internet ping $host_ip OK failed:$PING_RESULT% max:$PING_MAX_LOSS%"
        else
            echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
                "- VPN ping $host_ip OK failed:$PING_RESULT% max:$PING_MAX_LOSS%"
        fi
    fi
  else
    if [ "$VERBOSE_DEBUG" = "yes" ]; then
        if [ "$1" = "internet" ]; then
            echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
                "- Internet ping $host_ip ERROR failed:$PING_RESULT% max:$PING_MAX_LOSS%"
        else
            echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
                "- VPN ping $host_ip ERROR failed:$PING_RESULT% max:$PING_MAX_LOSS%"
        fi
    fi


    RET_PING_HOST="ERROR"
    if [ "$VERBOSE_DEBUG" = "yes" ]; then
        if [ "$1" = "internet" ]; then
                echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
                        "- Internet ping failed $host_ip"
        else
                echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
                        "- VPN ping failed $host_ip"
        fi
    fi


    if [ "$2" != "keep" ] && [ "$3" != "keep" ] && [ "$4" != "keep" ]; then
            #ping failed remove the IP from the random ping pool PING_IP_LIST
            remove_ip_from_array PING_IP_LIST[@] "$host_ip"
            PING_IP_LIST=("${RET_MODIFIED_ARRAY[@]}")
    fi
  fi
}


# checks the inconsistent return of ping and only return the "packet loss" integer
# example returns
# ping with < 100% packet loss
#   8 packets transmitted, 8 received, 0% packet loss, time 3588ms
# ping with 100% packet loss
#   6 packets transmitted, 0 received, +3 errors, 100% packet loss, time 2547ms
function get_packet_loss(){
    passed=$1
    unset RET_GET_PACKET_LOSS

    #Debian returns 0%
    #ret=`echo "$passed" | gawk -F"," '{print \$3}' | gawk -F "%" '{print \$1}' | tr -d ' '`
    # BSD returns as 0.0%
    ret=`echo "$passed" | /usr/local/bin/gawk -F"," '{print \$3}' | gawk -F "%" '{print \$1}' | /usr/local/bin/gawk -F "." '{print \$1}' | tr -d ' '`
    errors=`echo "$ret" | /usr/bin/grep -c "errors"`

    if [ "$errors" = "0" ]; then
        RET_GET_PACKET_LOSS=$ret
    else
        #failure string detected, run grep again
	RET_GET_PACKET_LOSS=`echo "$passed" | gawk -F"," '{print \$4}' | gawk -F "%" '{print \$1}' | tr -d ' '`
    fi
    return
}
