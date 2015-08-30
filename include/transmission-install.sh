#!/bin/bash
# script to allow the webUI to install the required software
LANG=en_US.UTF-8
export LANG

# update system to latest first since transmission may depend on a latest lib
echo "running system update first .... this may take a bit" > /usr/local/pia/cache/cmd_runner.txt
/usr/local/pia/system-update.sh

#apt-get install -y transmission-cli transmission-daemon cifs-utils
pkg install transmission transmission-cli transmission-daemon >> /usr/local/pia/cache/cmd_runner.txt 2>> /usr/local/pia/cache/cmd_runner.txt
if [ $? -ne 0 ]; then
  echo 'fatal error while installing transmission. please install by hand' >> /usr/local/pia/cache/cmd_runner.txt
  exit 1
fi


# make sure it is not running yet
killall transmission-daemon
#update-rc.d transmission-daemon remove


echo 'transmission installed - rebooting system' >> /usr/local/pia/cache/cmd_runner.txt
printf "\n\nCMDDONE" >> /usr/local/pia/cache/cmd_runner.txt
sleep 2 #give webUI time to fetch CMDDONE

/sbin/reboot