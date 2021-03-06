#!/bin/bash
# script to test different ping timeout settings using the ip_cache file
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'

PING_COMMAND_VPN="ping -qn -i 0.5 -w 1 -W 0.5 -I $IF_TUNNEL 8.8.8.8 2>/dev/null | $CMD_GREP -c \", 0% packet loss\""
PING_COMMAND_INTERNET="ping -qn -i 0.5 -w 1 -W 0.5 -I $IF_EXT 8.8.8.8 2>/dev/null | $CMD_GREP -c \", 0% packet loss\""


#read list of IPs into IP_LIST array
IFS=$'\r\n' IP_LIST=($(cat "/usr/local/pia/ip_list.txt" | tail -n+2))

stat_cnt_ok=0
stat_cnt_er=0
for ip in ${IP_LIST[@]}
do

	pingthis=`echo "$PING_COMMAND_INTERNET" | sed -e "s/8.8.8.8/$ip/g"`

	for (( x=1 ; x <= 50 ; x++ ))
	do
		res=`eval $pingthis`

		if [ "$res" != "1" ] || [ "$?" != "1" ]; then
			echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- ($x/50) ping failed for $ip"
			stat_cnt_er=$(($stat_cnt_er + 1))
		else
			stat_cnt_ok=$(($stat_cnt_ok + 1))
		fi
	done
done

echo "$stat_cnt_ok checks OK"
echo "$stat_cnt_er checks failed"

