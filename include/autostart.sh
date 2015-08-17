#!/bin/bash
# script to start services with rc.local
# you should not change this as it is controlled by the webbased PHP config script
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'


#read list of commands into array
IFS=$'\r\n' CMD_LIST=($(cat "/usr/local/pia/include/autostart.conf"))
for command in ${CMD_LIST[@]}
do
  if [ ! "$command" == "" ]; then
      eval "$command"
  fi
done