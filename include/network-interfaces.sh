#!/bin/bash
# script to update network settings in rc.conf under FreeBSD
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'

em0=""
em1=""
nameservers=""

#setup default route
if [ ! -z "${IF_DEFAULTROUTER}" ] ; then
  grep "defaultrouter" /etc/rc.conf &> /dev/null
  if [ $? -eq 0 ]; then
    sed 's/defaultrouter=.*/defaultrouter="'"${IF_DEFAULTROUTER}"'"/g' /etc/rc.conf > /tmp/rc.conf
    mv /tmp/rc.conf /etc/rc.conf
  else
    echo 'defaultrouter="'${IF_DEFAULTROUTER}'"' >> /etc/rc.conf
  fi
fi


#setup em0
if [ "${IF_ETH0_DHCP}" = 'yes' ]; then
  sed 's/ifconfig_em0=.*/ifconfig_em0="DHCP"/g' /etc/rc.conf > /tmp/rc.conf
  mv /tmp/rc.conf /etc/rc.conf
else

  if [ ! -z "${IF_ETH0_IP}" ] && [ ! -z "${IF_ETH0_SUB}" ]; then
    em0="${em0}inet ${IF_ETH0_IP} netmask ${IF_ETH0_SUB}"
  fi

  sed 's/ifconfig_em0=.*/ifconfig_em0="'"${em0}"'"/g' /etc/rc.conf > /tmp/rc.conf
  mv /tmp/rc.conf /etc/rc.conf

  #apply DNS when set
  for dns_srv in "${NAMESERVERS[@]}"
  do
    nameservers="${nameservers}${dns_srv}\n"
  done
  nameservers="${nameservers}\n"
  echo "$nameservers" > /etc/resolv.conf
fi


if [ "${IF_ETH1_DHCP}" = 'yes' ] && [ ! -z "${IF_ETH1_IP}" ] && [ ! -z "${IF_ETH1_SUB}" ]; then
  sed 's/ifconfig_em1=.*/ifconfig_em1="DHCP"/g' /etc/rc.conf > /tmp/rc.conf
  mv /tmp/rc.conf /etc/rc.conf

else
  if [ ! -z "${IF_ETH1_IP}" ] && [ ! -z "${IF_ETH1_SUB}" ]; then
    em1="${em1}inet ${IF_ETH1_IP} netmask ${IF_ETH1_SUB}"
  fi

  sed 's/ifconfig_em1=.*/ifconfig_em1="'"${em1}"'"/g' /etc/rc.conf > /tmp/rc.conf
  mv /tmp/rc.conf /etc/rc.conf

fi
