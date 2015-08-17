#!/bin/bash
# script to allow the webUI to install the required software
LANG=en_US.UTF-8
export LANG

# update system to latest first since transmission may depend on a latest lib
/usr/local/pia/system-update.sh

apt-get install -y transmission-cli transmission-daemon cifs-utils

# make sure it is not running yet
killall transmission-daemon
update-rc.d transmission-daemon remove


reboot