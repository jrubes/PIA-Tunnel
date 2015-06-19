#!/bin/bash

# fix an issue caused by modifying /etc/init.d/open-vm-tools
# added 19.06.2015 - remove after a few months
rm /etc/init.d/open-vm-tools
yes '' | apt-get update
yes '' | apt-get install open-vm-tools


# non interactive system updates
# http://debian-handbook.info/browse/wheezy/sect.automatic-upgrades.html
export DEBIAN_FRONTEND=noninteractive
yes '' | apt-get update -qq -y ; apt-get -qq -y -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" upgrade