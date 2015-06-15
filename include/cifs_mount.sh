#!/bin/bash
# mounts the network drive
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

mounted=`mount | grep "${CIFS_MOUNT}"`

if [ "${mounted}" = "" ]; then
  mount -t cifs -o credentials=/pia/smbpasswd.conf,iocharset=utf8,noatime "${CIFS_SHARE}" "${CIFS_MOUNT}"
fi