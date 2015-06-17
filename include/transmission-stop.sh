#!/bin/bash
# kills the transmission daemon
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'


killall transmission-daemon

if [ "${CIFS_AUTO}" = 'yes' ]; then
    sync
    /pia/include/cifs_umount.sh
fi

if [ "${CIFS_SHARE}" != "" ] && [ "${CIFS_MOUNT}" != "" ]; then
  #remove firewall rules allowing CIFS traffic
  /pia/include/cifs_fwclose.sh
fi