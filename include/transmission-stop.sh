#!/bin/bash
# kills the transmission daemon
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'


echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")" - killing transmission-daemon"
killall transmission-daemon

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