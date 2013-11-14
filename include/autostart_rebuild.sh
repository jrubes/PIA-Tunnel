#!/bin/bash
# rebuilds autostart.conf
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

cont="/pia/pia-forward stop quite\n"
cont="${cont}rm -f /pia/cache/session.log\n"
cont="${cont}rm -f /pia/cache/status.txt\n"
cont="${cont}rm -f /pia/cache/webgui_port.txt\n"
cont="${cont}/pia/pia-status\n"


if [ "${DAEMON_ENABLED}" = 'yes' ]; then
	first="${MYVPN[0]}"

	cont="${cont}rm -f /pia/cache/status.txt\n"
	cont="${cont}rm -f /pia/cache/php_pia-start.log\n"
    cont="${cont}echo -e \"connecting to ${first}\\\n\\\n\" > /pia/cache/session.log\n"
	cont="${cont}bash -c \"/pia/pia-start ${first} &>> /pia/cache/session.log ; /pia/pia-daemon &>/pia/cache/pia-daemon.log &\" &>/dev/null &\n"
fi

cont="${cont}\n"

echo -e $cont > '/pia/include/autostart.conf'
echo 'OK'