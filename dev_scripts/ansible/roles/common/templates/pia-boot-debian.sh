#!/bin/bash

# PROVIDE: PIAINFOS
# BEFORE:  LOGIN
# REQUIRE: NETWORKING

case "$1" in
*)
        # start with a fresh /etc/issue
        echo > /etc/issue

        # add current commit state of PIA-Tunnel
        PIAVER=`cd /pia ; git log -n 1 | gawk -F" " '{print $2}' | head -n 1`
        printf "\n\nPIA-Tunnel version: $PIAVER\n\n" >> /etc/issue

        /sbin/ifconfig eth0 2> /dev/null 1> /dev/null
        if [ $? -eq 0 ]; then
            eth0IP=`/sbin/ifconfig eth0 | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`
            echo "eth0 IP: $eth0IP" >> /etc/issue
        else
            echo "emo IP: ERROR: interface not found" >> /etc/issue
        fi


        /sbin/ifconfig eth1 2> /dev/null 1> /dev/null
        if [ $? -eq 0 ]; then
            eth1IP=`/sbin/ifconfig eth1 | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`
            echo "eth1 IP: $eth1IP" >> /etc/issue
        else
            echo "eth1 IP: interface not found" >> /etc/issue
        fi

        # auto start loads default firewall rules and more....
        #/pia/include/autostart.sh &
        exit 0
        ;;
stop)
        echo "nothing to stop - this handles autostart only"
	;;

esac
