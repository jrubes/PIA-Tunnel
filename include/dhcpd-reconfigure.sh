#!/bin/bash
# script to generate a new /etc/dhcp/dhcpd.conf based on a string passed in on $1
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'


echo "$1" > '/usr/local/etc/dhcpd.conf';