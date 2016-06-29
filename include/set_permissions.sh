#!/bin/bash
#script to create links to the pia scripts and set permissions

LANG=en_US.UTF-8
export LANG

#set file permissions as needed
chmod -R 0750 /usr/local/pia
find /usr/local/pia -type d -exec chmod 0750 {} \;
find /usr/local/pia -type f -exec chmod 0640 {} \;


FILES="pia-start pia-stop pia-status pia-update pia-settings"
FILES="$FILES pia-setup pia-forward  pia-prepare-ovpn pia-daemon"
for f in $FILES
do
	if [ -f "/sbin/$f" ]; then
		rm "/sbin/$f"
	fi
	ln -s "/usr/local/pia/$f" "/sbin/$f"
	chmod ug+x "/usr/local/pia/$f"
done

#handle files in include, these don't get /bin/ links
FILES="fw-iptables-forward.sh fw-iptables-no-forward.sh fw-pf-forward.sh fw-pf-no-forward.sh"
FILES="$FILES build_ping_list.sh first_boot.sh fix_settings.sh commands.sh network-interfaces.sh"
FILES="$FILES network-restart.sh autostart.sh dhcpd-reconfigure.sh dhcpd-start.sh log_fetch.sh"
FILES="$FILES dhcpd-stop.sh dhcpd-status.sh update_root.sh fw_get_forward_state.sh autostart_rebuild.sh"
FILES="$FILES sockd-dante-start.sh sockd-dante-status.sh sockd-dante-stop.sh sockd-dante-reconfigure.sh"
FILES="$FILES dhcpd-service.sh functions.sh  ping.sh ovpn_kill.sh set_permissions.sh"
FILES="$FILES sockd-3proxy-status.sh sockd-3proxy-start.sh sockd-3proxy-stop.sh"
FILES="$FILES socks-start.sh socks-stop.sh socks-status.sh cifs_mount.sh cifs_umount.sh"
FILES="$FILES transmission-start.sh transmission-stop.sh transmission-install.sh transmission-config.sh"
FILES="$FILES cifs_fwopen.sh cifs_fwclose.sh fw-close.sh up_internet.sh shutdown.sh"
for f in $FILES
do
    chmod ug+rx "/usr/local/pia/include/$f"
done

#reset-pia is special - ug+x but no /bin link
chmod ug+x "/usr/local/pia/reset-pia"
chmod ug+x "/usr/local/pia/system-update.sh"