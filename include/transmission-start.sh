#!/bin/bash
# ensures that the storage drive is mounted, then starts the transmission client
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

mounted=`mount | grep "${CIFS_MOUNT}"`

if [ "${mounted}" = "" ]; then

  if [ "${TRANSMISSION_ENABLED}" = 'yes' ]; then
      cont="${cont}\n"
  fi


  /pia/include/cifs_mount.sh

  if [ "$?" != "0" ]; then
    echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
	  "- error mounting drive"
    exit 1
  fi
fi



transmission-daemon  -g /etc/transmission-daemon/