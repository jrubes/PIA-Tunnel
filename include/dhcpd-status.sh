#!/bin/bash
# script to start dhcpd, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'


service isc-dhcp-server status 2>&1