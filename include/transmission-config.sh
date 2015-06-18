#!/bin/bash
# set download location
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

escaped=`echo "$CIFS_MOUNT" | sed -e 's./.\\\/.g'`


sed -i "s/\"download-dir\".*/\"download-dir\": \"${escaped}\",/" /etc/transmission-daemon/settings.json

sed -i "s/\"incomplete-dir\".*/\"incomplete-dir\": \"${escaped}\/incomplete\",/" /etc/transmission-daemon/settings.json


sed -i "s/\"rpc-authentication-required\".*/\"rpc-authentication-required\": \"${TRANSMISSION_AUTH_REQUIRED}\",/" /etc/transmission-daemon/settings.json

sed -i "s/\"rpc-username\".*/\"rpc-username\": \"${TRANSMISSION_USER}\",/" /etc/transmission-daemon/settings.json
sed -i "s/\"rpc-password\".*/\"rpc-password\": \"${TRANSMISSION_PASSWORD}\",/" /etc/transmission-daemon/settings.json

sed -i "s/\"rpc-whitelist\".*/\"rpc-whitelist\": \"${TRANSMISSION_WHITELIST}\",/" /etc/transmission-daemon/settings.json