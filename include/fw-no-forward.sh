#!/bin/bash
# these are the firewall settings used when the tunnel is not active.
LANG=en_US.UTF-8
export LANG

# load a default firewall
source '/pia/settings.conf'
iptables -F
iptables -t nat -F
iptables -t nat -X
iptables -t mangle -F
iptables -t mangle -X
iptables -P INPUT DROP
iptables -P FORWARD DROP
iptables -P OUTPUT ACCEPT
iptables -A INPUT -m state --state RELATED,ESTABLISHED -j ACCEPT

#allow dhcpd traffic if enabled
if [ "$DHCPD_ENABLED1" = 'yes' ] || [ "$DHCPD_ENABLED2" = 'yes' ]; then
    iptables -A INPUT -i $IF_EXT -p udp --dport 67:68 --sport 67:68 -j ACCEPT

    iptables -A INPUT -i $IF_INT -p udp --dport 67:68 --sport 67:68 -j ACCEPT
fi

#allow dhcp traffic if interface is not static
if [ "$IF_ETH0_DHCP" = 'yes' ]; then
	iptables -A OUTPUT -o $IF_EXT -p udp --dport 67:68 --sport 67:68 -j ACCEPT
fi
if [ "$IF_ETH1_DHCP" = 'yes' ]; then
	iptables -A OUTPUT -o $IF_INT -p udp --dport 67:68 --sport 67:68 -j ACCEPT
fi


#allowing incoming ssh traffic
if [ ! -z "${FIREWALL_IF_SSH[0]}" ]; then
  for interface in "${FIREWALL_IF_SSH[@]}"
  do
    iptables -A INPUT -i "$interface" -p tcp --dport 22 -j ACCEPT
    iptables -A OUTPUT -o "$interface" -m state --state RELATED,ESTABLISHED -j ACCEPT
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- SSH has been enabled for interface: $interface"
	fi
  done
fi


#allowing incoming traffic to web UI
if [ ! -z "${FIREWALL_IF_WEB[0]}" ]; then
  for interface in "${FIREWALL_IF_WEB[@]}"
  do
    iptables -A INPUT -i "$interface" -p tcp --dport 80 -j ACCEPT
    iptables -A OUTPUT -o "$interface" -m state --state RELATED,ESTABLISHED -j ACCEPT
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- webUI has been enabled for interface: $interface"
	fi
  done
fi


#disable forwarding
echo 0 > /proc/sys/net/ipv4/ip_forward