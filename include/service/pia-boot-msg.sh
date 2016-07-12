#!/bin/bash

# PROVIDE: PIAINFOS
# BEFORE:  LOGIN
# REQUIRE: NETWORKING

case "$1" in
*)
        if [ ! -f '/usr/local/pia/settings.conf' ]; then
          # will not exist after a reset
          source '/usr/local/pia/commands.sh'
          if [ "$OS_TYPE" = "Linux" ]; then
            $IF_EXT='eth0'
          else
            $IF_EXT='em0'
          fi

          # commands.sh creates settings so remove it again
          rm '/usr/local/pia/settings.conf'

        else
          source '/usr/local/pia/settings.conf'
        fi


        # start with a fresh /etc/issue
        echo > /etc/issue

        # add current commit state of PIA-Tunnel
        PIAVER=`cd /usr/local/pia ; $CMD_GIT log -n 1 | $CMD_GAWK -F" " '{print $2}' | head -n 1`
        printf "\n\nPIA-Tunnel version: $PIAVER\n\n" >> /etc/issue

        if [ "$OS_TYPE" = "Linux" ]; then
            $CMD_IP addr show "$IF_EXT" 2> /dev/null 1> /dev/null
            if [ $? -eq 0 ]; then
              eth0IP=`$CMD_IP addr show "$IF_EXT" | $CMD_GREP -w "inet" | $CMD_GAWK -F" " '{print $2}' | $CMD_CUT -d/ -f1`
              echo "$IF_EXT IP: $eth0IP" >> /etc/issue
            else
              echo "ERROR: interface(EXT:$IF_EXT) not found" >> /etc/issue
            fi

        else
            $CMD_IP "$IF_EXT" 2> /dev/null 1> /dev/null
            if [ $? -eq 0 ]; then
              eth0IP=`$CMD_IP "$IF_EXT" | $CMD_GREP -w "inet" | $CMD_GAWK -F" " '{print $2}' | $CMD_CUT -d/ -f1`
              echo "$IF_EXT IP: $eth0IP" >> /etc/issue
            else
              echo "ERROR: interface(EXT:$IF_EXT) not found" >> /etc/issue
            fi
        fi



        if [ "$OS_TYPE" = "Linux" ]; then
            $CMD_IP addr show "$IF_INT" 2> /dev/null 1> /dev/null
            if [ $? -eq 0 ]; then
              eth1IP=`$CMD_IP addr show "$IF_INT" | $CMD_GREP -w "inet" | $CMD_GAWK -F" " '{print $2}' | $CMD_CUT -d/ -f1`
              echo "$IF_INT IP: $eth1IP" >> /etc/issue
              /usr/local/pia/pia-settings "IF_INT_AVAILABLE" "yes"
            else
              /usr/local/pia/pia-settings "IF_INT_AVAILABLE" "no"
            fi

        else
            $CMD_IP "$IF_INT" 2> /dev/null 1> /dev/null
            if [ $? -eq 0 ]; then
              eth1IP=`$CMD_IP "$IF_INT" | $CMD_GREP -w "inet" | $CMD_GAWK -F" " '{print $2}' | $CMD_CUT -d/ -f1`
              echo "$IF_INT IP: $eth1IP" >> /etc/issue
              /usr/local/pia/pia-settings "IF_INT_AVAILABLE" "yes"
            else
              /usr/local/pia/pia-settings "IF_INT_AVAILABLE" "no"
            fi
        fi



		printf "\n\n" >> /etc/issue
        exit 0
        ;;
stop)
        echo "nothing to stop - this handles autostart only"
	;;

esac