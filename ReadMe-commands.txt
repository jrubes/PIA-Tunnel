# List of commands supported by the VM #
########################################

pia-start <location>
	Will create a new VPN connection and setup the firewall. Calling this command without a location will display a list of available locations.
	Example:
		pia-start
		pia-start Germany
		pia-start "UK London"

pia-stop
	Will kill openvpn, reset the firewall and restart the network interfaces.
	
pia-status
	Will display your VPN IP address and the port forwarded to 192.168.10.101
	
pia-update
	Will update files within /pia/ from https://github.com/KaiserSoft/PIA-Tunnel/
	
pia-setup
	Will setup the scripts above and fix any permission issues.
	This command can also generate new login.conf and settings.conf files if the files are not found in /usr/local/pia/.

	

	
# Development Commands #
########################
The commands below this line are development scripts not indended to be run by the user.
* Use these at your own risk *

pia-forward start|stop|fix
	Support script to control the firewall. This script it used by other scripts
	and is not really intended for the user.
	"pia-forward fix" will reset the iptable rules, kill openvpn and restart networking
	This command is used to develop this VM. Do not use it!
	
pia-prepare-ovpn
	Support script to modify the .ovpn files provided by PIA.
	This command is used to develop this VM. Do not use it!
	
reset-pia
	*WARNING* Resets login.conf, deletes the system log files, dhcp cache file
	and generates new SSH keys. 
	You should reboot your system after running this command!
	This command is used to develop this VM. Do not use it!