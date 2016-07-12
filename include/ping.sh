#!/bin/bash
LANG=en_US.UTF-8
export LANG
# provides the ping command to the webUI

if [ "$2" != "" ]; then
  uptime > /usr/local/pia/cache/tools_ping.txt
  echo "command used: ping -n -i 0.5 -w 4 -W 0.5 -I $2 $1" >> /usr/local/pia/cache/tools_ping.txt
  echo "" >> /usr/local/pia/cache/tools_ping.txt
  RET=`ping -n -i 0.5 -w 4 -W 0.5 -I "$2" "$1"`
  echo "$RET" >> /usr/local/pia/cache/tools_ping.txt
  echo "" >> /usr/local/pia/cache/tools_ping.txt
  echo "" >> /usr/local/pia/cache/tools_ping.txt
  echo "PINGDONE" >> /usr/local/pia/cache/tools_ping.txt

else

  echo "any interface ping" > /usr/local/pia/cache/tools_ping.txt
  echo "ping -n -i 0.5 -w 4 -W 0.5 $1" >> /usr/local/pia/cache/tools_ping.txt
  echo "" >> /usr/local/pia/cache/tools_ping.txt
  RET=`ping -n -i 0.5 -w 4 -W 0.5 "$1"`
  #ping -n -i 0.5 -w 4 -W 0.5 "$1" 2>&1 >> /usr/local/pia/cache/tools_ping.txt
  echo "$RET" >> /usr/local/pia/cache/tools_ping.txt
  echo "" >> /usr/local/pia/cache/tools_ping.txt
  echo "" >> /usr/local/pia/cache/tools_ping.txt
  echo "PINGDONE" >> /usr/local/pia/cache/tools_ping.txt
fi


