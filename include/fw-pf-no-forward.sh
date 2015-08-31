#!/bin/bash
# these are the firewall settings used when the tunnel is not active.
LANG=en_US.UTF-8
export LANG

tcp_services = ""
udp_services = ""
icmp_types = "{ echoreq, unreach }"

# load a default firewall
source '/usr/local/pia/settings.conf'
set require-order yes



#### Normalization
#scrub provides a measure of protection against certain kinds of attacks based on incorrect handling of packet fragments
scrub in all

# Drop incoming everything
block in all
pass out all keep state

# activate spoofing protection for all interfaces
block in quick from urpf-failed


#allo in
pass in on em0 proto tcp from any to any port $tcp_services  keep state
pass in on em0 proto udp from any to any port $udp_services  keep state
pass in on em1 proto tcp from any to any port $tcp_services  keep state
pass in on em1 proto udp from any to any port $udp_services  keep state



#allow dhcpd traffic if enabled
if [ "$DHCPD_ENABLED1" = 'yes' ] || [ "$DHCPD_ENABLED2" = 'yes' ]; then
    pass in on $IF_EXT proto udp from any to any port 67,68  keep state

    pass in on $IF_INT proto udp from any to any port 67,68  keep state
fi

#allow dhcp traffic if interface is not static
if [ "$IF_ETH0_DHCP" = 'yes' ]; then
        pass out $IF_EXT proto udp from any to any port 67,68 keep state
fi
if [ "$IF_ETH1_DHCP" = 'yes' ]; then
        pass out $IF_INT proto udp from any to any port 67,68 keep state
fi


#allowing incoming ssh traffic
if [ ! -z "${FIREWALL_IF_SSH[0]}" ]; then
  for interface in "${FIREWALL_IF_SSH[@]}"
  do
    pass in on "$interface" proto tcp from any to any port 22  keep state
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