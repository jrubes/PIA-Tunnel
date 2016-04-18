#!/bin/bash
# script to start dhcpd, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'


if [ "$OS_TYPE" = "Linux" ]; then
  systemctl start isc-dhcp-server 2>&1
else
  service isc-dhcp-server start 2>&1
fi