#!/bin/bash
# script pulls pia_latest_changes.md from Github and copies it into the correct directory on the webserver
# run script as cronjob on server holding pia_latest_changes.md
# run every 3 hours
# 0 */3 * * *      /root/pull_latest_changes.sh > /dev/null
LANG=en_US.UTF-8

mkdir -p /tmp/piatmp
cd /tmp/piatmp
wget https://raw.githubusercontent.com/KaiserSoft/PIA-Tunnel/release_php-gui/pia_latest_changes.md &> /dev/null

mv /tmp/piatmp/pia_latest_changes.md /srv/www/htdocs/kaisersoft.net/pia_latest_changes.md

rm /tmp/piatmp/* &> /dev/null
rmdir /tmp/piatmp