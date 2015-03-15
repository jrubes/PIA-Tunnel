Project: PIA-Tunnel VM
----------------------
PIA-Tunnel is a series of scripts designed to share an openVPN connection with your network.
It supports port forwarding, failover locations, LAN segments and a SOCKS5 proxy.
The included web interface makes it easy to setup connections and get the system configured to
your network.
The included shell scripts make it possible to control the system from the command line as well.


PIA-Tunnel started out as a virtual machine for VMware Workstation, Player and ESXi but
is now being used on KVM, XEN, Hyper-V and other solutions.
PIA-Tunnel is currently being tested on a Raspberry Pi 1 Model B+, with promising results.
The combination turns PIA-Tunnel into a stand alone VPN router for your network.
Advanced setups may utilize two network adapters to completely isolate a network or system.

Documentation:	http://www.KaiserSoft.net/r/?PIADOCU  
Support:		http://www.KaiserSoft.net/r/?PIAFORUM  

Author: Mirko Kaiser, http://www.KaiserSoft.net  
Support development with Bitcoins !thank you!  
                   16moftUyJeyGSCHEtE8bPFE9Ubg4j3SdKG

First created in Germany on 2013-07-20  
License: New BSD License

Copyright (c) 2013, Mirko Kaiser, http://www.KaiserSoft.net  
All rights reserved.


Features
========
* Open by design! PIA-Tunnel is script based. No binaries
  with hidden features and you may roll your own setup by
  following the steps in 'Clean Installation Steps.txt'
* Complete network isolation with VM LAN segment (leak protection)
* Simple Web-interface and/or command line support
* SOCKS 5 proxy for LAN or VM LAN segment. proxifier.com compatible
* Port forwarding to 1 IP on your LAN or private VM LAN (reqires provider support)
* Supports PrivateInternetAccess.com and FrootVPN.com out of the box


SETUP
=====

Please follow the steps in this guide: http://www.KaiserSoft.net/r/?PIADOCU