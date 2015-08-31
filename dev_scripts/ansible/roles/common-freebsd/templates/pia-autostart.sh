#!/bin/bash

# PROVIDE: PIAINFOS
# BEFORE:  LOGIN
# REQUIRE: NETWORKING

case "$1" in
*)
        # start with a fresh /etc/issue
        echo > /etc/issue

        # add current commit state of PIA-Tunnel
        PIAVER=`cd /usr/local/pia ; /usr/local/bin/git log -n 1 | /usr/local/bin/gawk -F" " '{print $2}' | /usr/bin/head -n 1`
        printf "PIA-Tunnel version: $PIAVER\n\n" >> /etc/issue

        echo "My IPs are" >> /etc/issue
        EM0IP=`/sbin/ifconfig em0 | /usr/bin/grep -w "inet" | /usr/local/bin/gawk -F" " '{print $2}' | /usr/bin/cut -d/ -f1`
        if [ $? -eq 0 ]; then
            echo "em0: $EM0IP" >> /etc/issue
        else
            echo "emo: ERROR: interface not found" >> /etc/issue
        fi


        EM1IP=`/sbin/ifconfig em1 | /usr/bin/grep -w "inet" | /usr/local/bin/gawk -F" " '{print $2}' | /usr/bin/cut -d/ -f1`
        if [ $? -eq 0 ]; then
            echo "em1: $EM1IP" >> /etc/issue
        else
            echo "em1: interface not found" >> /etc/issue
        fi

        # auto start loads default firewall rules and more....
	/usr/local/pia/include/autostart.sh &
        exit 0
        ;;
stop)
        echo "nothing to stop - this handles autostart only"
	;;

esac
