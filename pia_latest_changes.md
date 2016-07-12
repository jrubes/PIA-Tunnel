PIA-Tunnel    
Release notes for PIA-Tunnel VM [rss feed](http://www.kaisersoft.net/pia_latest.xml)    


update 2016-07-12
=================
* removed Russian VPN locations for PIA since they can no longer be trusted. Source: https://www.privateinternetaccess.com/forum/discussion/21779/we-are-removing-our-russian-presence    
    

update 2016-06-17
=================
* fixed password change for webUI
* added eol rules for git, should be consistent now	  
	
	
update 2016-06-16
=================
* added latest OpenVPN files for PIA. These add Norway, Turkey, Italy and so on
* Removed "Hong Kong" from list of port forwarding locations. Source: https://www.privateinternetaccess.com/pages/client-support/#sixth   
    

	
update 2016-04-12
=================
* added openVPN files for <a href="https://www.ivpn.net">https://www.ivpn.net</a>
* iVPN support untested. please report any issues
* no port forwarding at this time but I will try to add it ASAP
    
    
    
update 2016-04-11
===============
* You can get this page as an rss feed by pointing your RSS reader to the following URL         
[http://www.kaisersoft.net/pia_latest.xml](http://www.kaisersoft.net/pia_latest.xml)    
<a href="http://www.kaisersoft.net/pia_latest.xml">http://www.kaisersoft.net/pia_latest.xml</a>
    
    
    
update 2016-04-06
===============
* custom firewall ports are now working and can be set under Advanced Settings
* added config option to set the location for htdocs (settings.conf)     
  HTDOCS_ROOT="/var/www"
    
    
    
update 2016-04-04
===============
* found an issue that would prevent an empty setting from accepting     
  a new value after it has been cleared. I don't think that this was causing    
  any issues before but it would break the new custom firewall rules
    
    
    
update 2016-04-01
===============
* added support for custom firewall TCP ports    
  Settings => Advanced => Firewall Settings    
  Ports listed here will be open even if the VPN is disconnected.
    
    
    
update 2015-11-19
===============
* added arm7l for Raspberry Pi 2
    
    
    
update 2015-09-16
===============
* fixed a bug configuring the second network interface for DHCP use. Before: iface eth1 inet static, after: iface eth1 inet dhcp
    
    
    
update 2015-09-14
===============
* Added Virtual Box setup instructions to the new manual
* Updated the "Credits" section
    
    
    
update 2015-09-13
===============
* VM now waits until it receives an IP from the DHCP server. This should fix any issues where the LAN IP was not displayed after booting.
* added network.log to log the state of the network before and after connecting to the VPN
    
    
    
bug 2015-09-01
===============
* there is a bug in the code counting the available updates. The 90+ updates have been in 
the development branch but not in the release branch. Applying the update will only reset the counter since there are no release updates.
Will try to get a fix out soonish....
    
    
    
update 2015-05-12
===============
* had some time to work on the new manual. still a mess but it is slowly getting there.
The new manual can be found under "Tools"
    
    
    
updated 2015-05-10
===============
* added configuration option to allow incoming SNMP traffic through the firewall. This setting is disabled by default.
    
    
    
update 2015-05-06
===============
* **WARNING** major changes! Don't update yet unless you want to use the latest and greatest features. PLEASE REPORT ANY ISSUES!!!!
* removed most of the code that binds PIA-Tunnel to PrivateInternetAccess.com.      
  It should be possible to use this VM with most VPN providers soon.
* PrivateInternetAccess.com: added openVPN files for TCP and UDP connections.     
  UDP may be enabled in "General Settings" => "VPN Provider"
* ALL connections are now prefixed with a custom provider prefix. "PIAtcp/France" will create a TCP VPN tunnel to France and "PIAudp/France" will do the same using the UDP protocol.
* this update breaks your current PIA-Daemon configuration. You need to set new failover locations (Settings).
* the new changes may break some CMD line tools. I'll have to rewrite some parts to support the new dynamic format.
    
    
    
update 2015-04-28
===============
* I have received reports that a recent update degraded VPN performance for high speed connections (+1MB/s). The issue appears to be caused by a switch from a UDP to a TCP based VPN connections.      
I will implement an option to select which protocol to use ASAP.
    
    
    
update 2015-04-08
===============
* sry for the delay. This updates the connection files to match PIA's latest changes. VPN should work once again.
Please logout of the webUI to update your list of available VPN locations.
    
    
    
update 2015-04-04
===============
* their appears to be an issue with keeping a VPN connection up when there is more then a few KB of load on the tunnel. I suspect the issue is related to the latest openSSL bugs and the resulting patches. I will investigate ASAP.
    
    
    
update 2015-03-21
===============
* added an alternative SOCKS5 server package "3proxy" for i686 and arm6l. Looks like this one handles load a bit better.
  Please report any issues or if it improves performance.    
  Ensure the proxy server is not running then switch the software under "Settings" => "SOCKS 5 Proxy Server".
    
    
    
update 2015-03-20
===============
* The old SOCKS5 server configuration was verbose for testing. This is not required anymore and
  has been changed with last release. Please disable the SOCKS5 server on at least one interface
  to generate a new configuration file.  
  The current log file could be quite large. You may login as root and execute the following command to clear it.   
  echo "" >  /var/log/sockd.log
* Do not connect both network interfaces to the same network or use IPs in the same range!
  I have been working on the documentation and noticed that the VPN may not connect
  when both adapters are connected to the same network. In my case eth0 was set to 192.168.1.240
  and eth1 to 192.168.1.25 with a subnet of 255.255.255.0   
  Changing eth1 to 192.168.2.25 and disconnecting the network cable appears to have fixed it.
* retrieving this list before an update should work now
* few updates to the new manual
    
    
    
update 2015-03-16
===============
* added release notes to the update client - this box.  
  These notes are updated before you apply an update and will list any important changes.
* Optimized javascript on "Overview" page
* Added "Refresh Overview" setting to control the refreshing interval of the overview page
* Added Ping Utility to "Tools"
* webUI will be reloaded automatically from "Rebooting VM...." page
* Updated SOCKS5 server to dante 1.4.1
* Few optimizations here and there....
* Added <a href="http://parsedown.org/">Parsedown</a> to help generate these notes
* Working on a new Documentation. Local link under "Tools" or <a href="http://www.kaisersoft.net/pia_doc/index.html">New Manual</a>     
  Special thanks to Alan Diamond for rescuing the documentation from the grips of Microsoft ;)
* Added support for the Raspberry Pi 1 Model B+ and probably other ARM based devices. This is still experimental but it looks very promising.  
The RasbPi will act as a stand alone VPN router for you network with support for all PIA-Tunnel features.  
<a href="http://www.kaisersoft.net/pia_doc/index.html#pi_setup">Rasberry Pi Setup Instructions</a>. These will be turned into an installation script later on.
