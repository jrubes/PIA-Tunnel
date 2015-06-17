#!/bin/bash
# script opens port to allow CIFS traffic
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

if [ "${CIFS_INTERFACE}" = "any" ] || [ "${CIFS_INTERFACE}" = "eth0" ]; then
  iptables -D OUTPUT -o eth0 -p tcp --dport 445 -j ACCEPT
fi

if [ "${CIFS_INTERFACE}" = "any" ] || [ "${CIFS_INTERFACE}" = "eth1" ]; then
  iptables -D OUTPUT -o eth1 -p tcp --dport 445 -j ACCEPT
fi