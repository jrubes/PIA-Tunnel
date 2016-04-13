#!/bin/bash

# PROVIDE: PIAINFOS
# BEFORE:  LOGIN
# REQUIRE: NETWORKING

case "$1" in
*)
		source '/usr/local/pia/settings.conf'
		
        # start with a fresh /etc/issue
        echo > /etc/issue

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
        exit 0
        ;;
stop)
        echo "nothing to stop - this handles autostart only"
	;;

esac