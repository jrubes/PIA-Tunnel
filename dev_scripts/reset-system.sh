#!/bin/bash
# script will reset the system. use before cloning or after resotring ... just to be save

#clear existing ssh key
cd /etc/ssh/ ; rm -f *key*
if [ -f "/usr/sbin/dpkg-reconfigure" ]; then
        /usr/sbin/dpkg-reconfigure openssh-server
fi


echo "" > /root/.bash_history
cd /tmp/ ; rm -rf *
#delete all files in /var/log
find /var/log -type f -delete

if [ -f /tmp/dhcpd.leases ]; then
        rm -f /tmp/dhcpd.leases* ; touch /tmp/dhcpd.leases
fi

sync && /sbin/sysctl vm.drop_caches=3 && swapoff -a && swapon -a

echo "All done, please reboot or shutdown now"