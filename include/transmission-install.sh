#!/bin/bash
# script to allow the webUI to install the required software
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'

# update system to latest first since transmission may depend on a latest lib
echo "running system update first .... this may take a bit" > /usr/local/pia/cache/cmd_runner.txt
/usr/local/pia/system-update.sh


if [ "$OS_TYPE" = "Linux" ]; then
  apt-get install -y transmission-cli transmission-daemon cifs-utils
else
  pkg install transmission transmission-cli transmission-daemon >> /usr/local/pia/cache/cmd_runner.txt 2>> /usr/local/pia/cache/cmd_runner.txt
fi
if [ $? -ne 0 ]; then
  echo 'fatal error while installing transmission. please install by hand' >> /usr/local/pia/cache/cmd_runner.txt
  exit 1
fi

ret=$("$CMD_GREP" -c "CMD_TCCLI" /usr/local/pia/settings.conf)
if [ "$ret" -eq 0 ]; then
  CMD_TCCLI=$(whereis -b transmission-cli | $CMD_GAWK -F" " '{print $2}')
  echo "CMD_TCCLI='$CMD_TCCLI'" >> /usr/local/pia/settings.conf
fi



# make sure it is not running yet
killall transmission-daemon
#update-rc.d transmission-daemon remove


echo 'transmission installed - rebooting system' >> /usr/local/pia/cache/cmd_runner.txt
printf "\n\nCMDDONE" >> /usr/local/pia/cache/cmd_runner.txt
sleep 2 #give webUI time to fetch CMDDONE

/sbin/reboot