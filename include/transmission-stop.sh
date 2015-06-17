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