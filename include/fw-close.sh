#!/bin/bash
# these are the firewall settings used when the tunnel is not active.
LANG=en_US.UTF-8
export LANG

# close everything down. this is used in an emergency
iptables -P INPUT DROP
iptables -P FORWARD DROP
iptables -P OUTPUT DROP
iptables -F
iptables -t nat -F
iptables -t nat -X
iptables -t mangle -F
iptables -t mangle -X
iptables -P INPUT DROP
iptables -P FORWARD DROP
iptables -P OUTPUT DROP