#!/bin/bash
# unmounts the network drive
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'

mounted=`mount | $CMD_GREP "${CIFS_MOUNT}"`

if [ "${mounted}" != "" ]; then
  sync
  umount "${CIFS_MOUNT}"

  if [ "${CIFS_SHARE}" != "" ] && [ "${CIFS_MOUNT}" != "" ]; then
    #remove firewall rules allowing CIFS traffic
    /usr/local/pia/include/cifs_fwclose.sh
  fi
fi