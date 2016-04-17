#!/bin/bash
# script to start the socks server, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'


# make sure sockd is not currently running
socks_stat=`/usr/local/pia/include/sockd-3proxy-status.sh`
if [ "${socks_stat}" = 'running' ]; then
  LOOP_PROTECT=0
  while true; do

    socks_stat=`/usr/local/pia/include/sockd-3proxy-status.sh`
    if [ "${socks_stat}" = 'running' ]; then
      /usr/local/pia/include/sockd-3proxy-stop.sh

    elif [ "${socks_stat}" = 'not running' ]; then
      break
    fi

    #endless loop protect, about 30 seconds
    if [ "$LOOP_PROTECT" -eq 30 ]; then
      echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
          "- Unable to start 3proxy SOCKS5 Server. Please check /var/log/sockd.log"
      exit
    else
      sleep 1
      LOOP_PROTECT=$(($LOOP_PROTECT + 1))
    fi
  done
fi




#get IP of external interface
if [ "$OS_TYPE" = "Linux" ]; then
  EXT_IP=`$CMD_IP addr show "$IF_EXT" | $CMD_GREP -w "inet" |  $CMD_GAWK -F" " '{print $2}' |  $CMD_CUT -d/ -f1`
  TUN_IP=`$CMD_IP addr show "$IF_TUNNEL" | $CMD_GREP -w "inet" |  $CMD_GAWK -F" " '{print $2}' |  $CMD_CUT -d/ -f1`
  INT_IP=`$CMD_IP addr show "$IF_INT" | $CMD_GREP -w "inet" |  $CMD_GAWK -F" " '{print $2}' |  $CMD_CUT -d/ -f1`
else
  EXT_IP=`$CMD_IP "$IF_EXT" 2>/dev/null  | $CMD_GREP -w "inet" |  $CMD_GAWK -F" " '{print $2}' |  $CMD_CUT -d/ -f1`
  TUN_IP=`$CMD_IP "$IF_TUNNEL" 2>/dev/null | $CMD_GREP -w "inet" |  $CMD_GAWK -F" " '{print $2}' |  $CMD_CUT -d/ -f1`
  INT_IP=`$CMD_IP "$IF_INT" 2>/dev/null | $CMD_GREP -w "inet" |  $CMD_GAWK -F" " '{print $2}' |  $CMD_CUT -d/ -f1`
fi


if [ "$TUN_IP" = "" ]; then
  echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
        "- no tunnel interface found. The VPN might be down."
  exit;
else
  if [ "$SOCKS_EXT_ENABLED" = 'yes' ]; then
    /usr/sbin/socks -i"$EXT_IP" -e"$TUN_IP" -p"$SOCKS_EXT_PORT" -d 2>&1
  fi
  if [ "$SOCKS_INT_ENABLED" = 'yes' ]; then
    /usr/sbin/socks -i"$INT_IP" -e"$TUN_IP" -p"$SOCKS_INT_PORT" -d 2>&1
  fi
fi




LOOP_PROTECT=0
while true; do

  socks_stat=`/usr/local/pia/include/sockd-3proxy-status.sh`
  if [ "$socks_stat" = "running" ]; then
    echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")\
        "- 3proxy SOCKS5 Server has been started."
    exit
  fi


  #endless loop protect, about 30 seconds
  if [ "$LOOP_PROTECT" -eq 30 ]; then
    echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
        "- Unable to start 3proxy SOCKS5 Server."
    exit
  else
    sleep 1
    LOOP_PROTECT=$(($LOOP_PROTECT + 1))
  fi
done