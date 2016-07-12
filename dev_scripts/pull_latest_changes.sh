#!/bin/bash
# script pulls changes-v2-release.md from Github and copies it into the correct directory on the webserver
# run script as cronjob on server holding changes-v2-release.md
# run every 3 hours
# 0 */3 * * *      /root/pull_latest_changes.sh > /dev/null
LANG=en_US.UTF-8
source '/usr/local/pia/settings.conf'

mkdir -p /tmp/piatmp
cd /tmp/piatmp
wget https://raw.githubusercontent.com/KaiserSoft/PIA-Tunnel/release-v2/changes-v2-release.md &> /dev/null

mv /tmp/piatmp/changes-v2-release.md "$HTDOCS_PATH/changes-v2-release.md"

rm /tmp/piatmp/* &> /dev/null
rmdir /tmp/piatmp