#!/bin/bash
# script to stop the socks server, used by the webUI
LANG=en_US.UTF-8
export LANG

LOOP_PROTECT=0
while true; do
   socks_stat=`/pia/include/sockd-3proxy-status.sh`
  if [ "${socks_stat}" = 'running' ]; then
    killall socks &> /dev/null

  elif [ "${socks_stat}" = 'not running' ]; then
    exit

  fi

  #endless loop protect, about 30 seconds
  if [ "$LOOP_PROTECT" -eq 30 ]; then
    echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
        "- Unable to stop 3proxy SOCKS5 Server. Please check /var/log/sockd.log"
    exit
  else
    sleep 1
    LOOP_PROTECT=$(($LOOP_PROTECT + 1))
  fi
done