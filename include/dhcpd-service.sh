#!/bin/bash
# script to start dhcpd, used by the webUI
LANG=en_US.UTF-8
export LANG


if [ "$1" = "enable" ]; then
  update-rc.d lighttpd enable
else
  update-rc.d lighttpd disable
fi