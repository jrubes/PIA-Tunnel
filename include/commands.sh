#!/bin/bash
# find paths to programs


CMD_GREP=$(whereis -b grep | gawk -F" " '{print $2}')
CMD_GIT=$(whereis -b git | gawk -F" " '{print $2}')
CMD_GAWK=$(whereis -b gawk | gawk -F" " '{print $2}')
CMD_PING=$(whereis -b ping | gawk -F" " '{print $2}')
CMD_IP=$(whereis -b ip | gawk -F" " '{print $2}')
CMD_CUT=$(whereis -b cut | gawk -F" " '{print $2}')
CMD_SED=$(whereis -b sed | gawk -F" " '{print $2}')
CMD_SUDO=$(whereis -b sudo | gawk -F" " '{print $2}')
CMD_NETSTAT=$(whereis -b netstat | gawk -F" " '{print $2}')



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

}


# store commands in settings as well
if [ ! -f "/usr/local/pia/settings.conf" ]; then
	write_commands_settings
fi

ret=$("$CMD_GREP" -c "CMD_SUDO" /usr/local/pia/settings.conf)
if [ "$ret" -eq 0 ]; then
	write_commands_settings
fi