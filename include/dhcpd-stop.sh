#!/bin/bash
# script to stop dhcpd, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'


if [ "$OS_TYPE" = "Linux" ]; then
  systemctl stop isc-dhcp-server 2>&1
else
  service isc-dhcp-server stop 2>&1
fi