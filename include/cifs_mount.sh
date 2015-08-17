#!/bin/bash
# mounts the network drive
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'

mounted=`mount | grep "${CIFS_MOUNT}"`

if [ "${mounted}" = "" ]; then

  if [ ! -d "${CIFS_MOUNT}" ]; then
    mkdir -p "${CIFS_MOUNT}"
  fi

  if [ "${CIFS_SHARE}" != "" ] && [ "${CIFS_MOUNT}" != "" ]; then
    #apply firewall rules to allow CIFS traffic
    /usr/local/pia/include/cifs_fwopen.sh
    mount -t cifs -o credentials=/usr/local/pia/smbpasswd.conf,iocharset=utf8,noatime "${CIFS_SHARE}" "${CIFS_MOUNT}"

  elif [ "${CIFS_MOUNT}" != "" ]; then

    mount "${CIFS_MOUNT}"
  fi

  if [ "$?" != "0" ]; then
    exit 1
  fi
fi