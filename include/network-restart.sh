#!/bin/bash
# script to restart all network interfaces, called by PHP GUI
LANG=en_US.UTF-8
export LANG

# this kills the VPN so make sure it is all down
/usr/local/pia/pia-daemon stop
/usr/local/pia/pia-stop
killall /usr/local/pia/pia-daemon &> /dev/null

# clear the cache
rm -f /usr/local/pia/cache/session.log
rm -f /usr/local/pia/cache/status.txt
rm -f /usr/local/pia/cache/webui-port.txt
rm -f /usr/local/pia/cache/php_pia-start.log
rm -f /usr/local/pia/cache/pia-daemon.log


# restart the network interface
ifdown em0 &>/dev/null && ifup em0 &>/dev/null
ifdown em1 &>/dev/null && ifup em1 &>/dev/null