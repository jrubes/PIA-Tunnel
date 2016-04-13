#!/bin/bash
# script to run the very first time the user boots the VM
# it will downloaded the latest pia, reset the system and reboot
LANG=en_US.UTF-8
export LANG

if [ -f '/usr/local/pia/settings.conf' ]; then
  echo "Skipping first_boot.sh because 'settings.conf' exists."
  exit;
fi



# this creates settings.conf
chmod u+x /usr/local/pia/pia-setup
/usr/local/pia/pia-setup

# load support files
source '/usr/local/pia/settings.conf'
source '/usr/local/pia/include/functions.sh'



ping_host_new "internet" "quick"
if [ "$RET_PING_HOST" != "OK" ]; then

  # Internet down, refuse to continue

  echo '' > /etc/issue
  printf "\n\nUnable to connect to the Internet. Please establish a connection and reboot the VM\n\n" >> /etc/issue

  # add current commit state of PIA-Tunnel
  PIAVER=`cd /usr/local/pia ; git log -n 1 | gawk -F" " '{print $2}' | head -n 1`
  printf "\n\nPIA-Tunnel version: $PIAVER\n\n" >> /etc/issue

  /sbin/ifconfig "$IF_EXT" 2> /dev/null 1> /dev/null
  if [ $? -eq 0 ]; then
      eth0IP=`/sbin/ifconfig "$IF_EXT" | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`
      echo "IF_EXT IP: $eth0IP" >> /etc/issue
  else
      echo "IF_EXT IP: ERROR: interface not found" >> /etc/issue
  fi


  /sbin/ifconfig "$IF_INT" 2> /dev/null 1> /dev/null
  if [ $? -eq 0 ]; then
      eth1IP=`/sbin/ifconfig "$IF_INT" | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`
      echo "$IF_INT IP: $eth1IP" >> /etc/issue
  else
      echo "$IF_INT IP: interface not found" >> /etc/issue
  fi


  printf "\n\n" >> /etc/issue
  exit 99;



else

  echo '' > /etc/issue
  printf "\n\n\tFetching the latest version of PIA-Tunnel ....\n\tPlease wait until this VM reboots and this message disappears.\n\n" >> /etc/issue

  # add current commit state of PIA-Tunnel
  PIAVER=`cd /usr/local/pia ; git log -n 1 | gawk -F" " '{print $2}' | head -n 1`
  printf "\n\nPIA-Tunnel version: $PIAVER\n\n" >> /etc/issue

  /sbin/ifconfig "$IF_EXT" 2> /dev/null 1> /dev/null
  if [ $? -eq 0 ]; then
      eth0IP=`/sbin/ifconfig "$IF_EXT" | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`
      echo "IF_EXT IP: $eth0IP" >> /etc/issue
  else
      echo "IF_EXT IP: ERROR: interface not found" >> /etc/issue
  fi


  /sbin/ifconfig "$IF_INT" 2> /dev/null 1> /dev/null
  if [ $? -eq 0 ]; then
      eth1IP=`/sbin/ifconfig "$IF_INT" | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`
      echo "$IF_INT IP: $eth1IP" >> /etc/issue
  else
      echo "$IF_INT IP: interface not found" >> /etc/issue
  fi


  printf "\n\n" >> /etc/issue

  cd /usr/local/pia ; $CMD_GIT reset --hard HEAD
  /usr/local/pia/pia-update
  /usr/local/pia/reset-pia
  printf "" > /etc/issu
  /sbin/shutdown -r now
fi