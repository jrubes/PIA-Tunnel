#!/bin/bash
# kills the transmission daemon
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'


echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")" - killing transmission-daemon"
LOOP_PROTECT=0
while true; do
  running=`ps aux | grep -c "transmission-daemon"`
  if [ "$running" -gt 1 ]; then
    killall "transmission-daemon"
  else
    break
  fi

  #endless loop protect, about 20 seconds
  if [ "$LOOP_PROTECT" -eq 20 ]; then
      echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
          "- endless loop protection triggered when attempting to shut down the torrent client."
      exit 1
  else
      sleep 1
      LOOP_PROTECT=$((LOOP_PROTECT + 1))
  fi
done


if [ "${CIFS_AUTO}" = 'yes' ]; then
    sync
    /pia/include/cifs_umount.sh
fi


#remove rules for webUI traffic
if [ ! -z "${FIREWALL_IF_WEB[0]}" ]; then
  for interface in "${FIREWALL_IF_WEB[@]}"
  do
    iptables -D INPUT -i "$interface" -p tcp --dport 9091 -j ACCEPT
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- remove firewall rule for transmission webUI on interface: $interface"
	fi
  done
fi