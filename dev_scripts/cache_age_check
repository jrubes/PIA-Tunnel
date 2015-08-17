#!/bin/bash

# script to check age checking functions

LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'
source '/usr/local/pia/include/functions.sh'


filechk="/usr/local/pia/ip_list.txt"
cache_age_check "$filechk"
echo "$filechk has $RET_CACHE_AGE_CHECK"

filechk="/usr/local/pia/cache/status.txt"
cache_age_check "$filechk"
echo "$filechk has $RET_CACHE_AGE_CHECK"