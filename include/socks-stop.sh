#!/bin/bash
# script to start the socks server, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'



if [ "$SOCKS_SERVER_TYPE" = "dante" ]; then
  /pia/include/sockd-dante-stop.sh
else
  /pia/include/sockd-3proxy-stop.sh
fi