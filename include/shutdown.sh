#!/bin/bash
# shutdown or reboot the system using "at"
# this allows the webserver to complete the request before the system begins
# shutting down AND using at prevents the webserver
# from hanging while executing the command

if [ "$1" = "shutdown" ]; then
    sh -c 'echo "sleep 2 && /sbin/shutdown -P now" | at now'
else
    sh -c 'echo "sleep 2 && /sbin/shutdown -r now" | at now'
fi