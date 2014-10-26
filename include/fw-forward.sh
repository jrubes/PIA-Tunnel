#!/bin/bash
# these are the firewall settings used when the tunnel is active.
LANG=en_US.UTF-8
export LANG
source '/pia/settings.conf'
source '/pia/include/functions.sh'
RET_FORWARD_PORT="FALSE"

#get default gateway of tunnel interface using "ip"
#get IP of tunnel Gateway
TUN_GATEWAY=`/sbin/ip route show | grep "0.0.0.0/1" | gawk -F" " '{print $3}'`

#get tunnel IP
TUN_IP=`/sbin/ip addr show $IF_TUNNEL 2> /dev/null | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`
if [ "$TUN_IP" = "" ]; then
	echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
	  "- FATAL SCRIPT ERROR, tunnel interface: '$IF_TUNNEL' does not exist!"
    exit 1;
fi

#get IP of external interface
EXT_IP=`/sbin/ip addr show $IF_EXT | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`

#current default gateway
EXT_GW=`ip route show | grep "default" | gawk -F" " '{print $3}'`



# check for default username and exit
check_default_username



#function to get the port used for port forwarding
# "returns" RET_FORWARD_PORT with the port number as the value or FALSE
function get_forward_port() {
  RET_FORWARD_PORT="FALSE"

  #this only works with pia
  get_provider
  if [ "$RET_PROVIDER_NAME" = "pia" ]; then

    #check if the client ID has been generated and get it
    if [ ! -f "/pia/client_id" ]; then
      head -n 100 /dev/urandom | md5sum | tr -d " -" > "/pia/client_id"
    fi
    PIA_CLIENT_ID=`cat /pia/client_id`
    PIA_UN=`sed -n '1p' /pia/login-pia.conf`
    PIA_PW=`sed -n '2p' /pia/login-pia.conf`
    TUN_IP=`/sbin/ip addr show $IF_TUNNEL | grep -w "inet" | gawk -F" " '{print $2}' | cut -d/ -f1`

    #get open port of tunnel connection
    TUN_PORT=`curl -ks -d "user=$PIA_UN&pass=$PIA_PW&client_id=$PIA_CLIENT_ID&local_ip=$TUN_IP" https://www.privateinternetaccess.com/vpninfo/port_forward_assignment | cut -d: -f2 | cut -d} -f1`

    #the location may not support port forwarding
    if [[ "$TUN_PORT" =~ ^[0-9]+$ ]]; then
      RET_FORWARD_PORT=$TUN_PORT
    else
      RET_FORWARD_PORT="FALSE"
    fi
  fi
}

#get open port of tunnel connection
get_forward_port
TUN_PORT=$RET_FORWARD_PORT
#the location may not support port forwarding
if [[ "$TUN_PORT" =~ ^[0-9]+$ ]]; then
	PORT_FW="enabled"
else
	PORT_FW="disabled"
fi

#apply iptables settings
iptables -F
iptables -t nat -F
iptables -t nat -X
iptables -t mangle -F
iptables -t mangle -X
iptables -P INPUT DROP
iptables -P OUTPUT DROP
iptables -P FORWARD DROP

#allow outgoing traffic from this machine as long as it is sent over the VPN
iptables -A OUTPUT -o $IF_TUNNEL -j ACCEPT

#allow incoming on this machine as long as it is related
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


#enable POSTROUTING?
if [ "$FORWARD_PUBLIC_LAN" = 'yes' ] || [ "$FORWARD_VM_LAN" = 'yes' ] || [ "$FORWARD_PORT_ENABLED" = 'yes' ]; then
  iptables -A POSTROUTING -t nat -o $IF_TUNNEL -j MASQUERADE
fi

#setup forwarding for public LAN
if [ "$FORWARD_PUBLIC_LAN" = 'yes' ]; then
  #iptables -A POSTROUTING -t nat -o $IF_TUNNEL -j MASQUERADE
  iptables -A FORWARD -i $IF_EXT -o $IF_TUNNEL -j ACCEPT
  iptables -A FORWARD -i $IF_TUNNEL -o $IF_EXT -m state --state RELATED,ESTABLISHED -j ACCEPT
  if [ "$VERBOSE_DEBUG" = "yes" ]; then
      echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
          "- forwarding $IF_TUNNEL => $IF_EXT enabled"
  fi
fi

#setup forwarding for private VM LAN
if [ "$FORWARD_VM_LAN" = 'yes' ]; then
  #iptables -A POSTROUTING -t nat -o $IF_TUNNEL -j MASQUERADE
  iptables -A FORWARD -i $IF_INT -o $IF_TUNNEL -j ACCEPT
  iptables -A FORWARD -i $IF_TUNNEL -o $IF_INT -m state --state RELATED,ESTABLISHED -j ACCEPT
  if [ "$VERBOSE_DEBUG" = "yes" ]; then
      echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
          "- forwarding $IF_TUNNEL => $IF_INT enabled"
  fi
fi

#setup port forwarding
if [ "$PORT_FW" = 'enabled' ] && [ "$FORWARD_PORT_ENABLED" = 'yes' ]; then
	iptables -A PREROUTING -t nat -p tcp --dport $TUN_PORT -j DNAT --to "$FORWARD_IP"
	iptables -A PREROUTING -t nat -p udp --dport $TUN_PORT -j DNAT --to "$FORWARD_IP"
	iptables -A FORWARD -i $IF_TUNNEL -p tcp --dport $TUN_PORT -d "$FORWARD_IP" -j ACCEPT
	iptables -A FORWARD -i $IF_TUNNEL -p udp --dport $TUN_PORT -d "$FORWARD_IP" -j ACCEPT
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- port forwaring $IF_TUNNEL => '$FORWARD_IP':$TUN_PORT enabled"
	fi
else
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- port forwaring $IF_TUNNEL => '$FORWARD_IP' has NOT been enabled"
	fi
fi

#allowing incoming ssh traffic
if [ ! -z "${FIREWALL_IF_SSH[0]}" ]; then
  for interface in "${FIREWALL_IF_SSH[@]}"
  do
    iptables -A INPUT -i "$interface" -p tcp --dport 22 -j ACCEPT
    iptables -A OUTPUT -o "$interface" -m state --state RELATED,ESTABLISHED -j ACCEPT
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- SSH enabled for interface: $interface"
	fi
  done
fi

#allowing incoming SOCKS traffic
if [ "$SOCKS_INT_ENABLED" = 'yes' ]; then
    iptables -A INPUT -i "$IF_INT" -p tcp --dport "$SOCKS_INT_PORT" -j ACCEPT
    iptables -A INPUT -i "$IF_INT" -p udp --dport "$SOCKS_INT_PORT" -j ACCEPT
    iptables -A OUTPUT -o "$IF_INT" -m state --state RELATED,ESTABLISHED -j ACCEPT
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- SOCKS enabled for interface: $IF_INT"
	fi
fi
if [ "$SOCKS_EXT_ENABLED" = 'yes' ]; then
    iptables -A INPUT -i "$IF_EXT" -p tcp --dport "$SOCKS_EXT_PORT" -j ACCEPT
    iptables -A INPUT -i "$IF_EXT" -p udp --dport "$SOCKS_EXT_PORT" -j ACCEPT
    iptables -A OUTPUT -o "$IF_EXT" -m state --state RELATED,ESTABLISHED -j ACCEPT
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- SOCKS enabled for interface: $IF_EXT"
	fi
fi

#allowing incoming traffic to web UI
if [ ! -z "${FIREWALL_IF_WEB[0]}" ]; then
  for interface in "${FIREWALL_IF_WEB[@]}"
  do
    iptables -A INPUT -i "$interface" -p tcp --dport 80 -j ACCEPT
    iptables -A OUTPUT -o "$interface" -m state --state RELATED,ESTABLISHED -j ACCEPT
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- webUI enabled for interface: $interface"
	fi
  done
fi



# setup default routes - 2>/dev/null needs to be fixed, check if exists first, then remove or keep
#echo "E: route delete default dev $IF_EXT"
route delete default dev $IF_EXT 2>/dev/null
#echo "E: route add default gw $TUN_GATEWAY dev $IF_TUNNEL"
route add default gw $TUN_GATEWAY dev $IF_TUNNEL 2>/dev/null

# Enable forwarding
echo 1 > /proc/sys/net/ipv4/ip_forward