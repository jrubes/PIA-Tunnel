#!/bin/bash
# checks if sockd is currently running
LANG=en_US.UTF-8
export LANG

# see if a process is running
pcnt=`ps asxww | grep -c "socks -i"`
if [ "$pcnt" -gt "1" ]; then
  # daemon should be running
  echo 'running'
  exit
fi


echo 'not running'
exit
