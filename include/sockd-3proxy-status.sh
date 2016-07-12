#!/bin/bash
# checks if sockd is currently running
LANG=en_US.UTF-8
export LANG

# see if a process is running
if [ "$OS_TYPE" = "Linux" ]; then
  pcnt=`ps asxww | $CMD_GREP -c "socks -i"`
else
  pcnt=`ps axww | $CMD_GREP -c "socks -i"`
fi

if [ "$pcnt" -gt "1" ]; then
  # daemon should be running
  echo 'running'
  exit
fi


echo 'not running'
exit
