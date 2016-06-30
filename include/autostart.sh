#!/bin/bash
# script to start services with rc.local
# you should not change this as it is controlled by the webbased PHP config script
LANG=en_US.UTF-8
export LANG

# always run this first
/usr/local/pia/include/first_boot.sh

# copy html dir to webspace to refresh any custom files
cp -r /usr/local/pia/htdocs/* $HTDOCS_PATH/


source '/usr/local/pia/settings.conf'

# check if namespace in case this system has been reset with 'reset-pia'
# untested on FreeBSD ATM  2016-06-20
if [ "$WEB_UI_NAMESPACE" = "" ]; then
  WEB_UI_NAMESPACE=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c12)
  echo "WEB_UI_NAMESPACE='$WEB_UI_NAMESPACE'" >> '/usr/local/pia/settings.conf'
fi
if [ "$WEB_UI_COOKIE_AUTH" = "" ]; then
  # this value is reset when running the setup wizard but let's start with something unknown
  WEB_UI_COOKIE_AUTH=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c40)
  echo "WEB_UI_COOKIE_AUTH='$WEB_UI_COOKIE_AUTH'" >> '/usr/local/pia/settings.conf'
fi


#read list of commands into array
IFS=$'\r\n' CMD_LIST=($(cat "/usr/local/pia/include/autostart.conf"))
for command in ${CMD_LIST[@]}
do
  if [ ! "$command" == "" ]; then
      eval "$command"
  fi
done