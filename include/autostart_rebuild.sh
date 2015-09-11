#!/bin/bash
# rebuilds autostart.conf
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'

cont="/pia/pia-forward stop quite\n"
cont="${cont}ls /pia/cache/session.log &> /dev/null && mv /pia/cache/session.log /pia/cache/session.log.reboot\n"
cont="${cont}ls /pia/cache/status.txt &> /dev/null && mv /pia/cache/status.txt /pia/cache/status.txt.reboot\n"
cont="${cont}ls /pia/cache/webui-port.txt &> /dev/null && mv /pia/cache/webui-port.txt /pia/cache/webui-port.txt.reboot\n"
cont="${cont}ls /pia/cache/webui-update_status.txt &> /dev/null && mv /pia/cache/webui-update_status.txt /pia/cache/webui-update_status.txt.reboot\n"
cont="${cont}ls /pia/cache/php_pia-start.log &> /dev/null && mv /pia/cache/php_pia-start.log /pia/cache/php_pia-start.log.reboot\n"
cont="${cont}ls /pia/cache/pia-daemon.log &> /dev/null && mv /pia/cache/pia-daemon.log /pia/cache/pia-daemon.log.reboot\n"
cont="${cont}ls /pia/cache/network.log &> /dev/null && mv /pia/cache/network.log /pia/cache/network.log.reboot\n"
cont="${cont}/pia/pia-status\n"


if [ "${DAEMON_ENABLED}" = 'yes' ]; then
	first="${MYVPN[0]}"

	cont="${cont}ls /pia/cache/status.txt &> /dev/null && mv /pia/cache/status.txt /pia/cache/status.txt.reboot.daemon\n"
	cont="${cont}ls /pia/cache/php_pia-start.log &> /dev/null && mv /pia/cache/php_pia-start.log /pia/cache/php_pia-start.log.daemon\n"
    cont="${cont}echo -e \"connecting to ${first}\\\n\\\n\" > /pia/cache/session.log\n"
	cont="${cont}bash -c \"/pia/pia-start daemon\" &>> /pia/cache/session.log &\n"
    #cont="${cont}bash -c \"/pia/pia-start daemon\"\n"
fi

cont="${cont}\n"

echo -e $cont > '/pia/include/autostart.conf'
echo 'OK'
