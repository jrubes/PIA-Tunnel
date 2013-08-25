#!/bin/bash
# script to stop dhcpd, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'


service isc-dhcp-server stop 2>&1