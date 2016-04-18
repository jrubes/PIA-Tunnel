#!/bin/bash
# script to start dhcpd, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'


if [ "$OS_TYPE" = "Linux" ]; then
  systemctl status isc-dhcp-server 2>&1
else
  service isc-dhcp-server status 2>&1
fi