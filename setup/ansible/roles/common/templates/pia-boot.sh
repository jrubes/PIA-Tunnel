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
        printf "\n\nPIA-Tunnel version: $PIAVER\n\n" >> /etc/issue

        /sbin/ifconfig em0 2> /dev/null 1> /dev/null
        if [ $? -eq 0 ]; then
            EM0IP=`/sbin/ifconfig em0 | /usr/bin/grep -w "inet" | /usr/local/bin/gawk -F" " '{print $2}' | /usr/bin/cut -d: -f2`
            echo "em0 IP: $EM0IP" >> /etc/issue
        else
            echo "emo IP: ERROR: interface not found" >> /etc/issue
        fi


        /sbin/ifconfig em1 2> /dev/null 1> /dev/null
        if [ $? -eq 0 ]; then
            EM1IP=`/sbin/ifconfig em1 | /usr/bin/grep -w "inet" | /usr/local/bin/gawk -F" " '{print $2}' | /usr/bin/cut -d: -f2`
            echo "em1 IP: $EM1IP" >> /etc/issue
        else
            echo "em1 IP: interface not found" >> /etc/issue
        fi

        # auto start loads default firewall rules and more....
        #/usr/local/pia/include/autostart.sh &
        exit 0
        ;;
stop)
        echo "nothing to stop - this handles autostart only"
	;;

esac
