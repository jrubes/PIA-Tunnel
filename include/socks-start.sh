#!/bin/bash
# script to start the socks server, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'


/usr/sbin/sockd -f /etc/sockd.conf -p /run/sockd.pid -D 2>&1