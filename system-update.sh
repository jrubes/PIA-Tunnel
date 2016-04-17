#!/bin/bash


# Debian
if [ -f /usr/bin/apt-get ];
then
	# non interactive system updates
	# http://debian-handbook.info/browse/wheezy/sect.automatic-upgrades.html
	export DEBIAN_FRONTEND=noninteractive
	yes '' | apt-get update -qq -y >> /usr/local/pia/cache/cmd_runner.txt 2>> /usr/local/pia/cache/cmd_runner.txt
    yes '' | apt-get -qq -y -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" upgrade >> /usr/local/pia/cache/cmd_runner.txt 2>> /usr/local/pia/cache/cmd_runner.txt
fi


# FreeBSD
if [ -f /usr/sbin/pkg ];
then
	/usr/sbin/pkg update >> /usr/local/pia/cache/cmd_runner.txt 2>> /usr/local/pia/cache/cmd_runner.txt
	/usr/sbin/pkg upgrade >> /usr/local/pia/cache/cmd_runner.txt 2>> /usr/local/pia/cache/cmd_runner.txt
fi
