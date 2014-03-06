#!/bin/bash
# checks if sockd is currently running
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

# try with pid first. this should be most accurate
sockd_pid=`cat /tmp/sockd.pid`
if [ ! -z "${sockd_pid}" ]; then
  # daemon should be running
  ps_out=`ps s "${sockd_pid}" | tail -n1 | gawk -F" " '{print $10}'`

  if [ "${ps_out}" = '/usr/sbin/sockd' ]; then
    echo 'running'
    exit
  fi

fi


# last attempt with general ps
ps_cnt=`ps asx |  grep -c "/usr/sbin/sockd"`
if [ $ps_cnt = 1 ]; then
  echo 'not running'
  exit;
else
  echo 'running'
  exit
fi