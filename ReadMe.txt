/*
  * Project: PIA Tunnel VM
  * Description: Linux virtual machine to be used as a VPN to network bridge
  *
  * Author: Mirko Kaiser, http://www.KaiserSoft.net
  * Copyright (C) 2013 Mirko Kaiser
  * First created in Germany on 2013-07-20
  * License: New BSD License
  *
	Copyright (c) 201, Mirko Kaiser, http://www.KaiserSoft.net
	All rights reserved.
*/

*WARNING* I have only been working on this for the past couple of days so the "PIA Tunnel VM" is still
in development mode. It will listen to on port 22 (SSH) and will allow root logins!
You have been warned!


The PIA Tunnel VM is a Debian 7 virtual machine for VMware Workstation, Player or ESXi. It bridges your LAN to a private VMware LAN segment, totally isolating any VMs on that LAN segment from your LAN and Internet connection. This should prevent any traffic from bypassing the VPN tunnel 
and getting on the LAN and out your Internet connection.


The PIA Tunnel VM is a minimal Debian 7 installation with minor modifications.
	* root is the only account. The password is "pia" without quotes.
		* change the root password before you do anything else!!!!
	* dhcpd is running on eth1 but only 192.168.10.101 may use port forwarding
	* ntpd is disabled but a cronjob executes ntpd -q
	* mtp-status removed
	* open-vmware-tools have been installed
	* installed openvpn and disabled the service
	* sshd running and allowing root logins
	* git installed and repo PIA script repo cloned into /pia/
		run pia-update to fetch new releases
	
VM Pia Tunnel hardware requirements
	* 1 CPU
	* 80MB RAM
	* Network adapter 1 to LAN (port 22 is open so I use the NAT option in VMware)
	* Network adapter 2 to private VMware LAN segment
		

		
# SETUP #
#########

1) Download the Image from  FOO

2) Extract and copy into your "Virtual Machines" directory

3) Add VM to VMware Player or Workstation

4) Ensure that the second network adapter is a member of a private vLAN segment
	4.a) Workstation
		* Select "Network Adapter 2"
		* Click "LAN Segments" => "Add"
		* Enter name of LAN segment. I use "VPN Bridge"
		* Click OK to close
		* Use Dropdown to select the LAN segment you just created and click OK
			Connect client VMs to this LAN segment and remove or disable their other network cards.
			
	4.b) Player
		* to be added

5) Check that the machine has one CPU and around 80MB of RAM. 
   PIA Tunnel VM will use around 53MB after a fresh boot so you should use your RAM elsewhere.

6) Start the VM. When asked if you moved or copied it, select "I copied it".

7) You should see the login prompt after a few minutes. Login with "root" and password "pia", no quotes.

8) Change your root password with the following command
	passwd

9) Generate new SSL certificates by running
	/pia/clear_settings

10) reboot the system! yes do it!
	reboot

11) edit /pia/login.conf and enter your PIA account name and password. nothing else!
		nano /pia/login.conf
	So the file should look like this:
		p1234567
		f5Gh7Sw2vNmFa12OlP

12) see if you have Internet access by sending a few pings to google
	 ping -c 5 google.com
	 
13) everything should be ready to create the VPN tunnel. you may now use the following commands
	pia-start <location>
		Will create a new VPN connection and setup the firewall. Calling this command without a location will display a list of available locations.
		Example:
			pia-start
			pia-start Germany
			pia-start "UK London"

	pia-stop
		Will kill openvpn, reset the firewall and restart the network interfaces.
		
	pia-status
		Will display your VPN IP address and the port forward to 192.168.10.101
		
	pia-update
		Will update files within /pia/ from https://github.com/KaiserSoft/PIA-Tunnel/
		
	pia-setup
		Will setup the scripts above. use this if you rolled your own VM image and would like to
		set permissions and create links to the scripts in /pia/
		
	clear_settings
		*WARNING* Deletes your login data, log files and generates new SSH keys. This resets /pia/ back to the original download state. You should reboot your system after running this command!
		
14) Switch to your "Internet VM" now and give it a try. All traffic should now be forwarded thorugh the 
	VPN tunnel.
		traceroute -n google.com
