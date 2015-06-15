#!/bin/bash
# unmounts the network drive
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

mounted=`mount | grep "${CIFS_MOUNT}"`

if [ "${mounted}" != "" ]; then
  sync
  umount "${CIFS_MOUNT}"
fi