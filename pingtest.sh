#!/bin/bash
LANG=en_US.UTF-8
export LANG

#return variables and static stuff
RET_PING_HOST=""
RET_FILE_IS_WRITABLE="no"
declare -a RET_MODIFIED_ARRAY
RET_FORWARD_PORT="FALSE"

# WARNING DO NOT CHANGE the ping command! ping_host uses sed to modify the string
PING_COMMAND="ping -qn -i 0.5 -w 4 -W 0.5 -I INTERFACE IP2TOPING 2>/dev/null | grep -c \", 0% packet loss\""
PING_PACKET_LOSS="ping -qn -i 0.5 -w 4 -W 0.5 -I INTERFACE IP2TOPING 2>/dev/null | grep \"packet loss\" | gawk -F\",\" '{print \$3}' | gawk -F \"%\" '{print \$1}' | tr -d ' '"

# ping result settings
PING_MAX_LOSS=40 #this is the max percent allowed to fail, if LESS then = OK

IF_EXT="eth0"


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
		#exit

  elif [ "$1" = "vpn" ]; then
    pingthis=`echo "$pingthis" | sed -e "s/IP2TOPING/$host_ip/g"`
    pingthis=`echo "$pingthis" | sed -e "s/INTERFACE/$IF_TUNNEL/g"`
    PING_RESULT=`eval $pingthis`
		#echo "$pingthis"
		#exit

  else
	#use self to ping via VPN first then via Internet
	# return "OK" on first success or "ERROR" on complete failure
	ping_host_new "vpn" "$host_ip" "keep"
	if [ "$RET_PING_HOST" = "OK" ]; then
		RET_PING_HOST="OK" #ping via VPN was good
		return
	else
		ping_host_new "internet" "$host_ip" "keep"
		if [ "$RET_PING_HOST" = "OK" ]; then
			RET_PING_HOST="OK" #ping via Internet was good
			return
		else
			RET_PING_HOST="ERROR" #VPN and Internet are down
			return
		fi
	fi
  fi

	# see if the ping failed or is OK
  #if [ "$PING_RESULT" = "1" ]; then
  if [ "$PING_RESULT" -lt "$PING_MAX_LOSS" ]; then
    RET_PING_HOST="OK"
		echo "ping OK with $PING_RESULT%, allowed to fail $PING_MAX_LOSS%"

  else
		echo "failed with $PING_RESULT%, allowed to fail $PING_MAX_LOSS%"
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


ping_host_new "internet" "cnet.com"