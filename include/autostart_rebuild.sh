#!/bin/bash
# rebuilds autostart.conf
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'

cont="/usr/local/pia/pia-forward stop quite\n"
cont="${cont}rm -f /usr/local/pia/cache/session.log\n"
cont="${cont}rm -f /usr/local/pia/cache/status.txt\n"
cont="${cont}rm -f /usr/local/pia/cache/webui-port.txt\n"
cont="${cont}rm -f /usr/local/pia/cache/webui-update_status.txt\n"
cont="${cont}rm -f /usr/local/pia/cache/php_pia-start.log\n"
cont="${cont}rm -f /usr/local/pia/cache/pia-daemon.log\n"


if [ "${DAEMON_ENABLED}" = 'yes' ]; then
	cont="${cont}rm -f /usr/local/pia/cache/status.txt\n"
	cont="${cont}rm -f /usr/local/pia/cache/php_pia-start.log\n"
	ret=$(ps -p 1 | grep -c systemd )
	if [ "$ret" -eq 1 ]; then
		# setup pia-daemon autostart with systemD
		systemctl enable pia-daemon.service
	else
		first="${MYVPN[0]}"
		cont="${cont}echo -e \"connecting to ${first}\\\n\\\n\" > /usr/local/pia/cache/session.log\n"
		cont="${cont}bash -c \"/usr/local/pia/pia-start daemon\" &>> /usr/local/pia/cache/session.log &\n"
	fi
fi




# mount/connect network drive
if [ "${CIFS_AUTO}" = 'yes' ]; then
    cont="${cont}/usr/local/pia/include/cifs_mount.sh\n"
fi




cont="${cont}\n"

echo -e $cont > '/usr/local/pia/include/autostart.conf'
echo 'OK'