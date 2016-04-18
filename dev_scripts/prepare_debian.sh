#!/bin/bash
# script to setup the ansible environment and run it


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

cd /usr/local/pia ; git checkout master
if [ $? -ne 0 ]; then
  echo "Fatal Error during checkout"
  exit 99
fi


printf "\n\n\nPlease enter the password for your "pi" account when prompted\n\n\n"
cd /usr/local/pia/dev_scripts/ansible/ && ansible-playbook -i hosts PIA-Tunnel_Raspberry.yml --ask-pass

