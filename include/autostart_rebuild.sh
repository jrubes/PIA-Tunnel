#!/bin/bash
# rebuilds autostart.conf
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

cont="/pia/pia-forward stop quite\n"
cont="${cont}rm -f /pia/cache/session.log\n"
cont="${cont}rm -f /pia/cache/status.txt\n"
cont="${cont}rm -f /pia/cache/webui-port.txt\n"
cont="${cont}rm -f /pia/cache/webui-update_status.txt\n"
cont="${cont}rm -f /pia/cache/php_pia-start.log\n"
cont="${cont}rm -f /pia/cache/pia-daemon.log\n"
cont="${cont}/pia/pia-status\n"


if [ "${DAEMON_ENABLED}" = 'yes' ]; then
	first="${MYVPN[0]}"

	cont="${cont}rm -f /pia/cache/status.txt\n"
	cont="${cont}rm -f /pia/cache/php_pia-start.log\n"
    cont="${cont}echo -e \"connecting to ${first}\\\n\\\n\" > /pia/cache/session.log\n"
	cont="${cont}bash -c \"/pia/pia-start daemon\" &>> /pia/cache/session.log &\n"
fi




# mount/connect network drive
if [ "${CIFS_AUTO}" = 'yes' ]; then
    cont="${cont}/pia/include/cifs_mount.sh\n"
fi




cont="${cont}\n"

echo -e $cont > '/pia/include/autostart.conf'
echo 'OK'