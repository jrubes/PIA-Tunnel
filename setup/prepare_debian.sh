#!/bin/bash
# script to setup the ansible environment and run it

if [ $(whoami) != "root" ]; then
    printf "\nERROR: you must be root to run this script\n"
    exit 99
fi

if [ ! -d '/root/.ssh' ]; then
    mkdir -p /root/.ssh
    chmod 077 /root/.ssh
fi
ssh-keyscan 127.0.0.1 > /root/.ssh/known_hosts

apt-get update -y && apt-get upgrade -y

apt-get install -y ansible git sshpass
if [ $? -ne 0 ]; then
  echo "Fatal Error unable to install required software"
  exit 99
fi

mkdir /usr/local/pia && git clone https://github.com/KaiserSoft/PIA-Tunnel.git /usr/local/pia
if [ $? -ne 0 ]; then
  echo "Fatal Error cloning repo"
  exit 99
fi

cd /usr/local/pia ; git checkout release-v2
if [ $? -ne 0 ]; then
  echo "Fatal Error during checkout"
  exit 99
fi


printf "\n\n\nPlease enter the password for your 'root' account when prompted\n\n\n"
cd /usr/local/pia/setups/ansible/ && ansible-playbook -i hosts PIA-Tunnel_Debian.yml --ask-pass
