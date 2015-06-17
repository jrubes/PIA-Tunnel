#!/bin/bash
# unmounts the network drive
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

mounted=`mount | grep "${CIFS_MOUNT}"`

if [ "${mounted}" != "" ]; then
  sync
  umount "${CIFS_MOUNT}"

  if [ "${CIFS_SHARE}" != "" ] && [ "${CIFS_MOUNT}" != "" ]; then
    #remove firewall rules allowing CIFS traffic
    /pia/include/cifs_fwclose.sh
  fi
fi