#!/bin/bash
LANG=en_US.UTF-8
export LANG
# provides the ping command to the webUI

if [ "$2" != "" ]; then
  echo "command used: ping -n -i 0.5 -w 4 -W 0.5 -I $2 $1" > /pia/cache/tools_ping.txt
  echo "" >> /pia/cache/tools_ping.txt
  ping -n -i 0.5 -w 4 -W 0.5 -I "$2" "$1" 2>> /pia/cache/tools_ping.txt >> /pia/cache/tools_ping.txt

else

  echo "any interface ping" > /pia/cache/tools_ping.txt
  echo "ping -n -i 0.5 -w 4 -W 0.5 $1" >> /pia/cache/tools_ping.txt
  echo "" >> /pia/cache/tools_ping.txt
  ping -n -i 0.5 -w 4 -W 0.5 "$1" 2>&1 >> /pia/cache/tools_ping.txt
fi


