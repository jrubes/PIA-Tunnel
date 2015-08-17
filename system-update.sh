#!/bin/bash


# Debian
if [ -f /usr/bin/apt-get ]; 
then
	# non interactive system updates
	# http://debian-handbook.info/browse/wheezy/sect.automatic-upgrades.html
	export DEBIAN_FRONTEND=noninteractive
	yes '' | apt-get update -qq -y ; apt-get -qq -y -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" upgrade
fi


# FreeBSD
if [ -f /usr/sbin/pkg ];
then
	pkg update
	pkg upgrade
fi
