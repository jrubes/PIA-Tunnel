#!/bin/bash
# script adds CMD options back to settings.conf
LANG=en_US.UTF-8
export LANG

ret=$("$CMD_GREP" -c "CMD_SUDO" /usr/local/pia/settings.conf)
if [ "$ret" -eq 0 ]; then
    if [ -f '/usr/local/bin/gawk' ]; then
      #FreeBSD
      echo 'CMD_GREP="/usr/bin/grep"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_GIT="/usr/local/bin/git"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_GAWK="/usr/local/bin/gawk"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_PING="/sbin/ping"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_IP="/sbin/ip"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_CUT="/usr/bin/cut"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_SED="/usr/bin/sed"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_SUDO="/usr/local/bin/sudo"' >> '/usr/local/pia/settings.conf'


    else
      # Debian
      echo 'CMD_GREP="/bin/grep"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_GIT="/usr/bin/git"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_GAWK="/usr/bin/gawk"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_PING="/sbin/ping"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_IP="/bin/ip"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_CUT="/usr/bin/cut"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_SED="/bin/sed"' >> '/usr/local/pia/settings.conf'
      echo 'CMD_SUDO="/usr/bin/sudo"' >> '/usr/local/pia/settings.conf'

    fi
fi