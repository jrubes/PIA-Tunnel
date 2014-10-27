#!/bin/bash
# script to generate a list of IPs to be used when pinging
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'
source '/pia/include/functions.sh'

# simulate packet loss
#tc qdisc del root dev eth0 2> /dev/null
#tc qdisc add dev eth0 root netem loss 50%
#tc qdisc change dev eth0 root netem loss 25%

HOSTS="startpage.com icann.org wikipedia.org internic.net"
HOSTS="$HOSTS google.com google.de"
HOSTS="$HOSTS wordpress.com hosteurope.de 1und1.de"
HOSTS="$HOSTS gnu.org kernel.org cnet.com zdnet.com"
HOSTS="$HOSTS whitehouse.gov strato.de hetzner.de"
HOSTS="$HOSTS gandi.net rackspace.com arin.net"
HOSTS="$HOSTS spiegel.de thetimes.co.uk nytimes.com slashdot.org"
HOSTS="$HOSTS kaisersoft.net ticket.workorderts.com"

#will contain a list of IPs that can be pinged
# source is the A record for the hosts listed above
aIPS[0]=""

#used for debug in this script
VERBOSE="yes"

IPCACHE="/pia/ip_list.txt"



#allow user to specify the ping rounds used to recheck IPs before they
# are stored in ip_list.txt
if [ "$1" != "" ]; then
  max_ip_rounds=$1
else
  max_ip_rounds=1
fi


#check if the IP cache is writable. no point in running if it is not
if [ -f "$IPCACHE" ]; then
  if [ ! -w "$IPCACHE" ]; then
    echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
      "- $IPCACHE is not writable?!?"
    exit
  fi
else
  touch "$IPCACHE" 2> /dev/null
  #check exit status
  if [ "$?" = "1" ]; then
    echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
      "- \"$IPCACHE\" is not writable?!?"
    exit
  fi
  #remove the empty file so aborting the process will not keep an empty file around
  rm -f "$IPCACHE" 2> /dev/null
fi


#loop over list above and get dig A for each one
ip_count=0
for h in $HOSTS
do

  echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
	"- Checking IPs for $h ...."

  #get list of IPs in the domain's A record
  RET_DIG=`dig A +short +time=3 "$h"`
  if [ "$?" = "1" ]; then
	echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
	"- dig failed with $RET_DIG"
	RET_DIG=""
  fi

  for ip in $RET_DIG
  do
    #$ip should now contain one of the records from 'dig'
    # ping each one and store them if they respond
    for (( x=1 ; x <= $max_ip_rounds ; x++ ))
    do
      # ping each host multiple times to ensure they are reliable
      ping_host_new "any" "$ip"
      if [ "$RET_PING_HOST" = "ERROR" ]; then
		#echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
		#	"- ping failed $x/$max_ip_rounds attempts - excluding $ip"
		break #stop on first sign of trouble
      fi
      sleep 0.2
    done

    # or store if the IP is "good"
    if [ "$RET_PING_HOST" = "OK" ]; then
      #check if IP is unique before adding it to the list
      is_ip_unique "$ip" aIPS[@]
      if [ "$RET_IP_UNIQUE" = "yes" ]; then
				aIPS[$ip_count]="$ip"
				ip_count=$((ip_count + 1))
      else
		if [ "$VERBOSE_DEBUG" = "yes" ]; then
			echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
		      "- $ip not unique"
		fi
      fi
    fi
  done

done
ip_count=$((ip_count - 1))  #adjust since the last operation is to
							#increment the count for the next array


if [ $ip_count -lt 11 ]; then
    echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
      "- did not find enough IPs to generate ip_list.txt. is you Internet working?"
    echo -e "\tplease wait a few minutes then run pia-setup again."
    #reset packet loss
    #tc qdisc del root dev eth0
    exit 99

else
  # aIPS is now a string over IPs separated by space
  # loop over and write to /pia/ip_list.txt
  echo "# generated on "$(date +"%Y-%m-%d %H:%M:%S") > "$IPCACHE"
  for ip in ${aIPS[@]}
  do
    echo "$ip" >> "$IPCACHE"
  done
fi

echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
  "- stored $ip_count IPs in ip_list.txt"



#reset packet loss
#tc qdisc del root dev eth0