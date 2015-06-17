#!/bin/bash
# mounts the network drive
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

mounted=`mount | grep "${CIFS_MOUNT}"`

if [ "${mounted}" = "" ]; then

  if [ "${CIFS_SHARE}" != "" ] && [ "${CIFS_MOUNT}" != "" ]; then
    mount -t cifs -o credentials=/pia/smbpasswd.conf,iocharset=utf8,noatime "${CIFS_SHARE}" "${CIFS_MOUNT}"

  elif [ "${CIFS_MOUNT}" != "" ]; then

    mount "${CIFS_MOUNT}"
  fi

  if [ "$?" != "0" ]; then
    exit 1
  fi
fi