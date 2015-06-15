#!/bin/bash
# script to allow the webUI to install the required software
LANG=en_US.UTF-8
export LANG

# update system to latest first since transmission may depend on a latest lib
/pia/system-update.sh

apt-get install -y transmission-cli transmission-daemon cifs-utils

# restart or start
killall transmission-daemon
transmission-daemon  -g /etc/transmission-daemon/