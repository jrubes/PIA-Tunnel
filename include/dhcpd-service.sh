#!/bin/bash
# script to start dhcpd, used by the webUI
LANG=en_US.UTF-8
export LANG

if [ "$OS_TYPE" = "Linux" ]; then
  if [ "$1" = "enable" ]; then
    systemctl enable isc-dhcp-server 2>&1
  else
    systemctl disable isc-dhcp-server 2>&1
  fi

else
  if [ "$1" = "enable" ]; then
    update-rc.d lighttpd enable 2>&1
  else
    update-rc.d lighttpd disable 2>&1
  fi
fi