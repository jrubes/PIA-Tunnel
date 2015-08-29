#!/bin/bash
# script to check if the Internet is available
LANG=en_US.UTF-8
export LANG

source '/usr/local/pia/settings.conf'
source '/usr/local/pia/include/functions.sh'

ping_host_new "any" "quick" "keep"
echo "$RET_PING_HOST"


if [ "$RET_PING_HOST" = "ERROR" ]; then
  exit 1
else
  exit 0
fi