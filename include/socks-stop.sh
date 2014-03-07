#!/bin/bash
# script to stop the socks server, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

# make sure sockd is not currently running
socks_stat=`/pia/include/socks-status.sh`
if [ "${socks_stat}" = 'not running' ]; then

  exit

elif [ "${socks_stat}" = 'running' ]; then

  LOOP_PROTECT=0
  while true; do
     socks_stat=`/pia/include/socks-status.sh`
    if [ "${socks_stat}" = 'running' ]; then
      killall sockd &> /dev/null

    elif [ "${socks_stat}" = 'not running' ]; then
      exit

    fi

    #endless loop protect, about 30 seconds
    if [ "$LOOP_PROTECT" -eq 30 ]; then
      echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
          "- Unable to stop Dante SOCKS 5 Proxy Server. Please check /var/log/sockd.log"
      exit
    else
      sleep 1
      LOOP_PROTECT=$(($LOOP_PROTECT + 1))
    fi
  done

else
  echo -e "[\e[1;33mwarn\e[0m] Unkown return from socks-status.sh. received: ${socks_stat}"
fi