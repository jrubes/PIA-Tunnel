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

The PIA Tunnel VM is a VMware virtual machine with two network cards. One connects to your LAN and the other to a virtual LAN segment. The virtual segment is not bridged to your LAN so any VMs using it will have to
send all traffic through the "PIA Tunnel VM". This prevents any kind of traffic leak onto your LAN and Internet.

The PIA Tunnel VM is a minimal Debian 7 installation with minor modifications.
	* root is the only account. The password is "pia" without quotes.
		* change the root password before you do anything else!!!!
	* dhcpd is running on eth1 but only 192.168.10.101 may use port forwarding
	* ntpd is disabled but a cronjob executes ntpd -q
	* mtp-status removed
	* installed openvpn and disabled the service
	* sshd running and allowing root logins
	* git installed and repo PIA script repo cloned into /pia/
		run pia-update to fetch new releases
	
	* Hardware requirements:
		* 1 CPU
		* 80MB RAM
		* Network card 1 to LAN (port 22 is open so I use the NAT option in VMware)
		* Network card 2 to private vLAN
		

		
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

12) see if you have Internet access by sending a few pings
		ping -c 5 google.com
