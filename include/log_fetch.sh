#!/bin/bash
# fetch the latest git log for the webui
source '/usr/local/pia/settings.conf'

val=`cd /usr/local/pia ; $CMD_GIT fetch origin &> /dev/null ; $CMD_GIT rev-list HEAD... origin/"$1" --count 2>/dev/null`

if [ "$val" = "0" ]; then
  dt=`date +%s`
  echo "$dt|$val" > /usr/local/pia/cache/webui-update_status.txt

  if [ ! -f "$HTDOCS_PATH/changes-v2-release.md" ]; then
    #fetch latest changelog - updated installations without the file
    cd /tmp
    mkdir piatmpget ; cd /tmp/piatmpget
    $CMD_WGET http://www.kaisersoft.net/changes-v2-release.md
    mv changes-v2-release.md "$HTDOCS_PATH/changes-v2-release.md"
    cd /tmp ; rm -rf /tmp/piatmpget
  fi

elif [ "$val" -gt "0" ]; then
  dt=`date +%s`
  echo "$dt|$val" > /usr/local/pia/cache/webui-update_status.txt

  #fetch latest changelog
  cd /tmp
  mkdir piatmpget ; cd /tmp/piatmpget
  $CMD_WGET http://www.kaisersoft.net/changes-v2-release.md
  mv changes-v2-release.md "$HTDOCS_PATH/changes-v2-release.md"
  cd /tmp ; rm -rf /tmp/piatmpget

else
  echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
    "- invalid information returned by git .... "\
    "$val"
fi