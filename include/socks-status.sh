#!/bin/bash
# script for the socks server, used by the webUI
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'


#checks status of sockd using pid in /run/sockd.pid
if [ ! -f "/tmp/sockd.pid" ]; then
  echo 'pid file not found'
  exit 99
fi

sockd_pid=`cat /tmp/sockd.pid`

if [ ! -z "${sockd_pid}" ]; then
  # daemon should be running
  ps_out=`ps s "${sockd_pid}" | tail -n1 | gawk -F" " '{print $10}'`

  if [ "${ps_out}" = '/usr/sbin/sockd' ]; then
    echo 'running'
    exit

  else
    echo 'not running'
    exit;
  fi

else
  echo 'not running'
  exit
fi