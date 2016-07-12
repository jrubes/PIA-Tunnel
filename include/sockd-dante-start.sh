#!/bin/bash
# script to start the socks server, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'


# make sure sockd is not currently running
socks_stat=`/usr/local/pia/include/sockd-dante-status.sh`
if [ "${socks_stat}" = 'running' ]; then
  LOOP_PROTECT=0
  while true; do

    socks_stat=`/usr/local/pia/include/sockd-dante-status.sh`
    if [ "${socks_stat}" = 'running' ]; then
      /usr/local/pia/include/sockd-dante-stop.sh

    elif [ "${socks_stat}" = 'not running' ]; then
      break
    fi

    #endless loop protect, about 30 seconds
    if [ "$LOOP_PROTECT" -eq 30 ]; then
      echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
          "- Unable to start Dante SOCKS 5 Proxy Server. Please check /var/log/sockd.log"
      exit
    else
      sleep 1
      LOOP_PROTECT=$(($LOOP_PROTECT + 1))
    fi
  done
fi





/usr/sbin/sockd -f /etc/sockd.conf -p /tmp/sockd.pid -D 2>&1




LOOP_PROTECT=0
while true; do

  socks_stat=`/usr/local/pia/include/sockd-dante-status.sh`
  if [ "${socks_stat}" = 'running' ]; then
    echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
        "- Dante SOCKS 5 Proxy Server has been started."
    exit

  fi


  #endless loop protect, about 30 seconds
  if [ "$LOOP_PROTECT" -eq 30 ]; then
    echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
        "- Unable to start Dante SOCKS 5 Proxy Server. Please check /var/log/sockd.log"
    exit
  else
    sleep 1
    LOOP_PROTECT=$(($LOOP_PROTECT + 1))
  fi
done