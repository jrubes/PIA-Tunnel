#!/bin/bash
# non interactive system updates
# http://debian-handbook.info/browse/wheezy/sect.automatic-upgrades.html
export DEBIAN_FRONTEND=noninteractive
yes '' | apt-get update -qq -y ; apt-get -qq -y -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" upgrade