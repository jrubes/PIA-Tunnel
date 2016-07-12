#!/bin/bash
# get current state of the iptables FORWARD chain, used by PHP webUI
LANG=en_US.UTF-8
export LANG

source '/usr/local/pia/settings.conf'
source '/usr/local/pia/include/functions.sh'

check_forward_state "${1}"
echo "$RET_FORWARD_STATE"