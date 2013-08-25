#!/bin/bash
# script to restart all network interfaces, called by PHP GUI
LANG=en_US.UTF-8
export LANG

ifdown eth0 &>/dev/null && ifup eth0 &>/dev/null
ifdown eth1 &>/dev/null && ifup eth1 &>/dev/null