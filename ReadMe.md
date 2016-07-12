Project: PIA-Tunnel VM
----------------------
PIA-Tunnel VM is a series of scripts designed to share an openVPN connection with your network. 
It is designed to run stand alone on a virtual machine or RaspberryPi.    
     
DO NOT run IA-Tunnel VM on a multiuser system because it contains scripts to allow the webUI to change the root 
password, apply firewall rules and other things. Users with command line access could use these scripts to gain root access!    

Overview
--------
PIA-Tunnel VM supports Debian 8, Raspberry Pi (Raspbian Lite) or FreeBSD (work in progress).   
It supports port forwarding, failover locations, LAN segments, offers a SOCKS5 proxy server (RasPi and FreeBSD), 
DHCP server and lease view (phpdhcpd) and the transmission torrent client with support for writing 
files onto a local NAS.
    
    
The included web interface makes it easy to setup connections and get the system configured to
your network.    
The included shell scripts make it possible to control the system from the command line as well.

PIA-Tunnel VM started out as a virtual machine for VMware Workstation, Player and ESXi 
with support for PrivateInternetAccess.com    
It has since been updated to offer
* support for more VPN proviers out of the box and offers support to add custom .ovpn files
* also tested on KVM, XEN, Hyper-V and other virtual machine solutions
* full support for Raspberry Pi which makes it possible to create a stand alone VPN router
    
    
Documentation:	http://www.KaiserSoft.net/r/?PIADOCU    
Support:	http://www.KaiserSoft.net/r/?PIAFORUM

Author: Mirko Kaiser, http://www.KaiserSoft.net    
Support development with Bitcoins !thank you!  16moftUyJeyGSCHEtE8bPFE9Ubg4j3SdKG    

First created in Germany on 2013-07-20    
License: New BSD License    

Copyright (c) 2016, Mirko Kaiser, http://www.KaiserSoft.net     
All rights reserved.


Features
========
* Open by design, script based with no binaries.
* Use your own Linux installation by following the steps in the "setup" subdirectory
* Can provide complete network isolation (leak protection)
* Simple Web-interface and/or command line support
* SOCKS 5 proxy for LAN or VM LAN segment. proxifier.com compatible
* Port forwarding to 1 IP on your LAN or private VM LAN (reqires VPN provider support)
* Supports PrivateInternetAccess.com, HideMyAss, iVPN, HideIPVPN and FrootVPN out of the box


Setup
=====
Documentation: http://www.KaiserSoft.net/r/?PIADOCU     
