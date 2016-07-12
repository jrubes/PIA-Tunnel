#!/bin/bash
# checks if sockd is currently running
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'

# try with pid first. this should be most accurate
sockd_pid=`cat /tmp/sockd.pid 2&> /dev/null`
if [ ! -z "${sockd_pid}" ]; then
  # daemon should be running
  if [ "$OS_TYPE" = "Linux" ]; then
    ps_out=`ps s "${sockd_pid}" | $CMD_TAIL -n1 | $CMD_GAWK -F" " '{print $10}'`
  else
    ps_out=`ps -p "${sockd_pid}" | $CMD_TAIL -n1 | $CMD_GAWK -F" " '{print $1}'`
  fi

  if [ "${ps_out}" = '/usr/sbin/sockd' ]; then
    echo 'running'
    exit
  fi

fi


# last attempt with general ps
# *WARNING* needs ww in ps or grep will not work on startup because the output is truncated!!
if [ "$OS_TYPE" = "Linux" ]; then
  ps_cnt=`ps asxww | $CMD_GREP -c "/usr/sbin/sockd"`
else
  ps_cnt=`ps axww | $CMD_GREP -c "/usr/sbin/sockd"`
fi
if [ "${ps_cnt}" = 0 ] || [ "${ps_cnt}" = 1 ]; then
  echo 'not running'
  exit;
else
  echo 'running'
  exit
fi