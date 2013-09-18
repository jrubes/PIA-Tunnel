/*
  * Project: PIA Tunnel VM
  * Description: Linux virtual machine to be used as a VPN to network bridge.
  *	  This VM will only work with the VPN service from https://www.privateinternetaccess.com/
  *
  * Author: Mirko Kaiser, http://www.KaiserSoft.net
  * Support the software with Bitcoins !thank you!: 157Gh2dTCkrip8hqj3TKqzWiezHXTPqNrV
  * Copyright (C) 2013 Mirko Kaiser
  * First created in Germany on 2013-07-20
  * License: New BSD License
  *
	Copyright (c) 201, Mirko Kaiser, http://www.KaiserSoft.net
	All rights reserved.
*/

The PIA Tunnel VM is a Debian 7 virtual machine for VMware Workstation, Player or ESXi. It bridges a private VMware LAN segment to the PIA VPN Network, totally isolating any VMs on that private LAN segment from your LAN and Internet connection. This should prevent any traffic from bypassing the VPN tunnel, getting on the LAN and out your Internet connection.

* Features
    * requires 1 CPU, 92MB RAM and a little over 1GB hard drive space
		* Edit: The VM will run with as little as 64MB but will rarely lock up after the boot loader screen
		  I don't know what is causing it but 92MB prevents this.
    * primary network adapter pulls IP from your LAN so the VM is "start and use" once setup
    * the secondary network adapter is running on 192.168.10.1 and is handing out IPs
	  in the range 192.168.10.101 to 192.168.10.151
    * the VM supports port forwarding and will route the port assigned by PIA to 192.168.10.101
    * the external IP and port can be checked at any time with the command "pia-status"
	* ssh running on eth0


* Linux details 
	* the VM is running a minimal Debian 7 installation. See for /pia/docs/
	* root is the only account. The password is "pia" without quotes.
		* change the root password before you do anything else!!!!
	* dhcpd is running on eth1 but only 192.168.10.101 will be configured
	  for automatic port forwarding
	* ntpd is disabled but a cronjob executes ntpd -q
	* open-vmware-tools have been installed
	* installed openvpn and disabled the service
	* sshd running and allowing root logins
	* git installed and repo PIA script repo cloned into /pia/
		run pia-update to fetch new releases
		
		
		
# SETUP #
#########

1) Download the compressed VMware OVF Template
     https://mega.co.nz/#!rMBiDaSY!RHZatU94kQAEndH3nln3MzbauYEbq9imWLsvw5Gqfcs

2) Extract the 7-Zip archive. 7-Zip can be found here: http://www.7-zip.org/

3) Workstation and Player
3.a) Add OVF Template to VMware Workstation or Player
	* The easy way: Double click on "PIA Tunnel.ovf" then on "Import" goto step 3.b)
	*
	* The hard way....
	* Start Player/Workstation and click File => Open...
	* Change file type to "All Files" (lower right corner above OK)
	* Select "PIA Tunnel.ovf" and click "Open" then "Import"
	
3.b) Ensure that the second network adapter is a member of a private vLAN segment
	* Select "Network Adapter 2"
	* Click "LAN Segments" => "Add"
	* Enter name of LAN segment. I use "VPN Bridge"
	* Click OK to close
	* Use Dropdown to select the LAN segment you just created and click OK
		Connect client VMs to this LAN segment and remove or disable their other network cards.
	* goto step 5

4) ESXi
4.a) Setup private VM LAN segment first
	* In vSphere Client
	1) Setup a private VM LAN segment
	  * select your ESXi server and choose "Configuration"
	  * Click on "Networking" => "Add Networking..."
	  * "Virtual Machine" => "Create a vSphere standard switch" uncheck any selected interfaces!
		The preview must list "No adapters" on the "Physical Adapters" side!
	  * Enter a network name, I use "VPN Network - PIA"
		  Double check the preview, it should look like this
		  https://github.com/KaiserSoft/PIA-Tunnel/blob/master/docs/esxi_private_network.png

4.b) Import the OVF Image
	  * Extract the file you downloaded. You should now have a folder with tree files
	  * "File" => "Deploy OVF Template..."
	  * Browse to the extracted files and select "PIA Tunnel.ovf" => "Next" => "Next"
	  * Give the VM a name and select a datastore to keep the machine on => "Next"
	  * I use "Thin Provision" since the VM will not change much
	  * Select your external Network on the "Network Mapping" screen
	  * Do not auto power the machine once deployment is complete
		
4.c) Configure VM
	  * Select the VM => "Edit Settings"
	  * Make sure that "Network adapter 1" is connected to the network with Internet access
		and that "Network adapter 2" is connected to the private LAN segment you created
		in step 1 above.
	  * RAM should be set to at least 92MB RAM. I have never seen the VM SWAP so 92MB is 
		tight but enough.
	  * Save the changes and power the VM on
		

5) Check that the machine has one CPU and around 92MB of RAM. 
   PIA Tunnel VM will use around 60MB after a fresh boot so you should use your RAM elsewhere.

6) Start the VM. When asked if you moved or copied it, select "I copied it".

7) You should see the login prompt after a few minutes. Login with "root" and password "pia", no quotes.

7.5) Run "dpkg-reconfigure keyboard-configuration" to change the keyboard setting. Default is German layout

8) Change your root password with the following command
	passwd

9) Generate new SSL certificates by running
	/pia/reset-pia

10) reboot the VM with the following command! yes, really, just do it :)
	reboot

11) edit /pia/login.conf and enter your PIA account name and password. nothing else!
		nano /pia/login.conf
	So the file should look something like this:
		p1234567
		f5Gh7Sw2vNmFa12OlP

12) see if you have Internet access by sending a few pings to Google
	 ping -c 5 google.com
	 
13) everything should be ready to create the VPN tunnel.
	*you should run "pia-update" before using any of the other commands for the first time*
	
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
		This command can also generate new login.conf and settings.conf files if the files are not found in /pia/.


14) Switch to your "Internet VM" now and give it a try. All traffic should be forwarded thorugh the 
	VPN tunnel.
		traceroute -n google.com

		

* Client VMs
	You may use the build in DHCP server or assign your own IPs to your client VMs. The PIA Tunnel VM is configured to use 192.168.10.0 on the second network adapter with a DHCP range of .101 to .151
		* The VM forwards the remote tunnel port only to the .101 IP!
	
	I use a dedicate VM for downloading on my network so I configure dhcpd to use a static assignment for .101. See the bottom of /etc/dhcp/dhcpd.conf for details.
	Restart your dhcp server with the following command after you change the config file.
		service isc-dhcp-server restart
		

* Advanced Tips and Tricks
	* configure the MYVPN settings in /pia/settings.conf then see /etc/rc.local how to enable pia-daemon
	  automatically after a system boot.
	  The IP and port will be printed on the console or use "pia-status" if you use remote access.

	* Use the "screen" command to start pia-daemon over an ssh connection. screen will keep pia-daemon
	  running after you disconnect.
			screen pia-daemon
	  detach by pressing CTRL+A CTRL+D
	  and reattach with
			screen -r
	  
	  
	  

	