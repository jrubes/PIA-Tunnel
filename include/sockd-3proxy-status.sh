#!/bin/bash
# checks if sockd is currently running
LANG=en_US.UTF-8
export LANG

# see if a process is running
# Debian pcnt=`ps asxww | /usr/bin/grep -c "socks -i"`
pcnt=`ps axww | /usr/bin/grep -c "socks -i"`
if [ "$pcnt" -gt "1" ]; then
  # daemon should be running
  echo 'running'
  exit
fi


echo 'not running'
exit
