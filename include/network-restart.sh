#!/bin/bash
# script to restart all network interfaces, called by PHP GUI
LANG=en_US.UTF-8
export LANG

# this kills the VPN so make sure it is all down
/pia/pia-daemon stop
/pia/pia-stop
killall /pia/pia-daemon &> /dev/null

# clear the cache
rm -f /pia/cache/session.log
rm -f /pia/cache/status.txt
rm -f /pia/cache/webgui_port.txt
rm -f /pia/cache/php_pia-start.log
rm -f /pia/cache/pia-daemon.log


# restart the network interface
ifdown eth0 &>/dev/null && ifup eth0 &>/dev/null
ifdown eth1 &>/dev/null && ifup eth1 &>/dev/null