#!/bin/bash
# generates a firewall file that may be loaded with pfctl
LANG=en_US.UTF-8
export LANG

source '/usr/local/pia/settings.conf'

fwfile="/usr/local/pia/firewall/fw-no-forward.conf"


# Interface definitions
echo 'tun_if = "tun0"' > "$fwfile"
echo 'ext_if = "em0"' >> "$fwfile"
echo 'int_if = "em1"' >> "$fwfile"
echo 'localnet = $int_if:network' >> "$fwfile"
echo 'set require-order yes' >> "$fwfile"

#### Normalization
#scrub provides a measure of protection against certain kinds of attacks based on incorrect handling of packet fragments
echo 'scrub in all' >> "$fwfile"

# activate spoofing protection for all interfaces
echo 'block in quick from urpf-failed' >> "$fwfile"

echo 'antispoof for $tun_if' >> "$fwfile"
echo 'antispoof for $ext_if' >> "$fwfile"
echo 'antispoof for $int_if' >> "$fwfile"

# drop Non-Routable Addresses
echo 'martians = "{ 127.0.0.0/8, 192.168.0.0/16, 172.16.0.0/12, 10.0.0.0/8, 169.254.0.0/16, 192.0.2.0/24, 0.0.0.0/8, 240.0.0.0/4 }" ' >> "$fwfile"
echo 'block drop out quick on { $tun_if, $ext_if, int_if } from any to $martians' >> "$fwfile"


# Drop incoming everything
echo 'block in all' >> "$fwfile"
echo 'pass out all keep state' >> "$fwfile"


#allow in
#pass in on em0 proto tcp from any to any port $tcp_services  keep state
#pass in on em0 proto udp from any to any port $udp_services  keep state
#pass in on em1 proto tcp from any to any port $tcp_services  keep state
#pass in on em1 proto udp from any to any port $udp_services  keep state



#allow dhcpd traffic if enabled
if [ "$DHCPD_ENABLED1" = 'yes' ] || [ "$DHCPD_ENABLED2" = 'yes' ]; then
    echo "pass in on $IF_EXT proto udp from any to any port { 67,68 } keep state" >> "$fwfile"

    echo "pass in on $IF_INT proto udp from any to any port { 67,68 } keep state" >> "$fwfile"
fi

#allow dhcp traffic if interface is not static
if [ "$IF_ETH0_DHCP" = 'yes' ]; then
        echo "pass out $IF_EXT proto udp from any to any port { 67,68 } keep state" >> "$fwfile"
fi
if [ "$IF_ETH1_DHCP" = 'yes' ]; then
        echo "pass out $IF_INT proto udp from any to any port { 67,68 } keep state" >> "$fwfile"
fi


#allowing incoming ssh traffic
if [ ! -z "${FIREWALL_IF_SSH[0]}" ]; then
  printf "\n#Allow ssh traffic\n" >> "$fwfile"

  for interface in "${FIREWALL_IF_SSH[@]}"
  do
    echo "pass in on $interface proto tcp from any to any port 22  keep state" >> "$fwfile"
    #iptables -A OUTPUT -o "$interface" -m state --state RELATED,ESTABLISHED -j ACCEPT
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- SSH has been enabled for interface: $interface"
	fi
  done
fi


#allowing incoming traffic to web UI
if [ ! -z "${FIREWALL_IF_WEB[0]}" ]; then
  printf "\n#Allow ssh traffic\n" >> "$fwfile"

  for interface in "${FIREWALL_IF_WEB[@]}"
  do
    echo "pass in on $interface proto tcp from any to any port 80  keep state" >> "$fwfile"
    #iptables -A OUTPUT -o "$interface" -m state --state RELATED,ESTABLISHED -j ACCEPT
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- webUI has been enabled for interface: $interface"
	fi
  done
fi


#disable forwarding
#echo 0 > /proc/sys/net/ipv4/ip_forward