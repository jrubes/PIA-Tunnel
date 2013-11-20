#!/bin/bash
# get current state of the iptables FORWARD chain, used by PHP webUI
LANG=en_US.UTF-8
export LANG

source '/pia/settings.conf'
source '/pia/include/functions.sh'

check_forward_state
echo "$RET_FORWARD_STATE"