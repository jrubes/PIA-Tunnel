#!/bin/bash
# script to start the socks server, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'



if [ "$SOCKS_SERVER_TYPE" = "dante" ]; then
  /usr/local/pia/include/sockd-dante-status.sh
else
  /usr/local/pia/include/sockd-3proxy-status.sh
fi