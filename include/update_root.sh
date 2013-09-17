#!/bin/bash
# script to change the root password
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'


if [ $# -eq 0 ]; then
  exit 1
fi

echo "root:$1" | chpasswd -c SHA512