#!/bin/bash
# find paths to programs

# need gawk first
if [ -f '/usr/local/bin/gawk' ]; then
  CMD_GAWK='/usr/local/bin/gawk'
elif [ -f '/usr/bin/gawk' ]; then
  CMD_GAWK='/usr/bin/gawk'
else
  CMD_GAWK=$(whereis -b gawk | gawk -F" " '{print $2}')
fi


CMD_GREP=$(whereis -b grep | $CMD_GAWK -F" " '{print $2}')
CMD_GIT=$(whereis -b git | $CMD_GAWK -F" " '{print $2}')
CMD_PING=$(whereis -b ping | $CMD_GAWK -F" " '{print $2}')
CMD_IP=$(whereis -b ip | $CMD_GAWK -F" " '{print $2}')
CMD_CUT=$(whereis -b cut | $CMD_GAWK -F" " '{print $2}')
CMD_SED=$(whereis -b sed | $CMD_GAWK -F" " '{print $2}')
CMD_SUDO=$(whereis -b sudo | $CMD_GAWK -F" " '{print $2}')
CMD_NETSTAT=$(whereis -b netstat | $CMD_GAWK -F" " '{print $2}')
CMD_TAIL=$(whereis -b tail | $CMD_GAWK -F" " '{print $2}')
CMD_WGET=$(whereis -b wget | $CMD_GAWK -F" " '{print $2}')

if [ -f '/usr/sbin/sockd' ]; then
  CMD_DANTECLI='/usr/sbin/sockd'
else
  CMD_DANTECLI=''
fi
if [ -f '/usr/sbin/socks' ]; then
  CMD_3PROXYCLI='/usr/sbin/socks'
else
  CMD_3PROXYCLI=''
fi


# write for webUI
function write_commands_settings() {

	if [ ! -f "/usr/local/pia/settings.conf" ]; then
		touch "/usr/local/pia/settings.conf"
	fi

	echo "CMD_GREP='$CMD_GREP'" >> /usr/local/pia/settings.conf
	echo "CMD_GIT='$CMD_GIT'" >> /usr/local/pia/settings.conf
	echo "CMD_GAWK='$CMD_GAWK'" >> /usr/local/pia/settings.conf
	echo "CMD_PING='$CMD_PING'" >> /usr/local/pia/settings.conf
	echo "CMD_IP='$CMD_IP'" >> /usr/local/pia/settings.conf
	echo "CMD_CUT='$CMD_CUT'" >> /usr/local/pia/settings.conf
	echo "CMD_SED='$CMD_SED'" >> /usr/local/pia/settings.conf
	echo "CMD_SUDO='$CMD_SUDO'" >> /usr/local/pia/settings.conf
    echo "CMD_NETSTAT='$CMD_NETSTAT'" >> /usr/local/pia/settings.conf
    echo "CMD_TAIL='$CMD_TAIL'" >> /usr/local/pia/settings.conf
    echo "CMD_WGET='$CMD_WGET'" >> /usr/local/pia/settings.conf
    echo "CMD_DANTECLI"='$CMD_DANTECLI'" >> /usr/local/pia/settings.conf
    echo "CMD_3PROXYCLI"='$CMD_3PROXYCLI'" >> /usr/local/pia/settings.conf

}


# store commands in settings.conf as well
if [ ! -f "/usr/local/pia/settings.conf" ]; then
	write_commands_settings
fi
