#!/bin/bash
# holds paths to commands as variables
# collect commands for now, then split into commands-debian.sh and commands-freebsd.sh, then use this file to source one of the two

if [ -f '/usr/local/bin/gawk' ]; then
  #FreeBSD
  CMD_GREP='/usr/bin/grep'
  CMD_GIT='/usr/local/bin/git'
  CMD_GAWK='/usr/local/bin/gawk'
  CMD_PING='/sbin/ping'
  CMD_IP='/sbin/ip'
  CMD_CUT='/usr/bin/cut'
  CMD_SED='/usr/bin/sed'


else
  # Debian
  CMD_GREP='/bin/grep'
  CMD_GIT='/usr/bin/git'
  CMD_GAWK='/usr/bin/gawk'
  CMD_PING='/sbin/ping'
  CMD_IP='/bin/ip'
  CMD_CUT='/usr/bin/cut'
  CMD_SED='/bin/sed'

fi