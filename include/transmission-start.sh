#!/bin/bash
# ensures that the storage drive is mounted, then starts the transmission client
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'
source '/pia/include/functions.sh'

# do not start if VPN is down
ping_host_new "vpn"
vpn_up=$RET_PING_HOST
if [ "$RET_PING_HOST" != "OK" ]; then
  echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
      "- VPN is down - refusing to start torrent client"
  exit 1
fi


mounted=`mount | grep "${CIFS_MOUNT}"`

if [ "${mounted}" = "" ]; then
  /pia/include/cifs_mount.sh

  if [ "$?" != "0" ]; then
    echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
      "- error mounting drive"
    exit 1
  fi
fi


if [ ! -d "$CIFS_MOUNT/incomplete" ]; then
  # create incomplete directory. this is set in transmission-config.sh and is hard coded for now
  mkdir -p "$CIFS_MOUNT/incomplete"
fi


#allowing incoming traffic to web UI
if [ ! -z "${FIREWALL_IF_WEB[0]}" ]; then
  for interface in "${FIREWALL_IF_WEB[@]}"
  do
    iptables -A INPUT -i "$interface" -p tcp --dport 9091 -j ACCEPT
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- transmission webUI enabled for interface: $interface"
	fi
  done
fi

echo -e "[info] "$(date +"%Y-%m-%d %H:%M:%S")" - starting transmission-daemon"
transmission-daemon  -g /etc/transmission-daemon/