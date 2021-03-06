#!/bin/bash
# script to change the root password
# EXIT:
#   0 = OK
#   1 = Error changing password
#   2 = Invalid password
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'

if [ $# -eq 0 ]; then
  exit 2
fi

#len=`expr length "$1"
len=${#1}

if [ "$len" -lt 3 ]; then
 exit 2
fi


if [ "$OS_TYPE" = "FreeBSD" ]; then
  echo "$1" | pw mod user root -h 0 2>/dev/null
else
  #Debian
  echo "root:$1" | chpasswd -c SHA512 2> /dev/null
fi

if [ $? = 0 ]; then
  # PW has been changed
  exit 0
else
  # something went wrong
  exit 1
fi
