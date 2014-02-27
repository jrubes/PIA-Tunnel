#!/bin/bash
# script to stop the socks server, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'


killall sockd 2>&1