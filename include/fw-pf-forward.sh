#!/bin/bash
# these are the firewall settings used when the tunnel is active.
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'
source '/usr/local/pia/include/functions.sh'
fwfile="/usr/local/pia/firewall/fw-forward.conf"
RET_FORWARD_PORT="FALSE"

#get default gateway of tunnel interface using "ip"
#get IP of tunnel Gateway
TUN_GATEWAY=`/usr/bin/netstat -rn -4 | /usr/bin/grep "0.0.0.0/1" | gawk -F" " '{print $2}'`

#get tunnel IP
TUN_IP=`/sbin/ifconfig $IF_TUNNEL 2> /dev/null | /usr/bin/grep -w "inet" | /usr/local/bin/gawk -F" " '{print $2}' | cut -d/ -f1`
if [ "$TUN_IP" = "" ]; then
	echo -e "[\e[1;31mfail\e[0m] "$(date +"%Y-%m-%d %H:%M:%S")\
	  "- FATAL SCRIPT ERROR, tunnel interface: '$IF_TUNNEL' does not exist!"
    exit 1;
fi

#get IP of external interface
EXT_IP=`/sbin/ifconfig $IF_EXT 2> /dev/null | /usr/bin/grep -w "inet" | /usr/local/bin/gawk -F" " '{print $2}' | /usr/bin/cut -d/ -f1`

#current default gateway
EXT_GW=`/usr/bin/netstat -rn -4 | /usr/bin/grep "default" | /usr/local/bin/gawk -F" " '{print $2}'`



# check for default username and exit
check_default_username



#function to get the port used for port forwarding
# "returns" RET_FORWARD_PORT with the port number as the value or FALSE
function get_forward_port() {
  RET_FORWARD_PORT="FALSE"

  #this only works with pia
  get_provider
  if [ "$RET_PROVIDER_NAME" = "PIAtcp" ] || [ "$RET_PROVIDER_NAME" = "PIAudp" ]; then

    #check if the client ID has been generated and get it
    if [ ! -f "/usr/local/pia/client_id" ]; then
      head -n 100 /dev/urandom | /sbin/md5 > "/usr/local/pia/client_id"
    fi
    PIA_CLIENT_ID=`cat /usr/local/pia/client_id`
    PIA_UN=`sed -n '1p' /usr/local/pia/login-pia.conf`
    PIA_PW=`sed -n '2p' /usr/local/pia/login-pia.conf`
    TUN_IP=`/sbin/ifconfig $IF_TUNNEL | /usr/bin/grep -w "inet" | /usr/local/bin/gawk -F" " '{print $2}' | /usr/bin/cut -d/ -f1`

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


#allow outgoing traffic from this machine as long as it is sent over the VPN
echo 'pass out ($tun_if) proto any from any to any keep state' >> "$fwfile"


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


#enable POSTROUTING?
if [ "$FORWARD_PUBLIC_LAN" = 'yes' ] || [ "$FORWARD_VM_LAN" = 'yes' ] || [ "$FORWARD_PORT_ENABLED" = 'yes' ]; then
  echo 'nat on $ext_if from $localnet to any -> ($tun_if)' >> "$fwfile"
fi

#setup forwarding for public LAN
if [ "$FORWARD_PUBLIC_LAN" = 'yes' ]; then
  echo 'nat on $tun_if from $localnet to any -> ($ext_if)' >> "$fwfile"

  if [ "$VERBOSE_DEBUG" = "yes" ]; then
      echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
          "- forwarding $IF_TUNNEL => $IF_EXT enabled"
  fi
fi

#setup forwarding for private VM LAN
if [ "$FORWARD_VM_LAN" = 'yes' ]; then
  echo 'nat on ($tun_if) from $localnet to any -> ($int_if)' >> "$fwfile"

  if [ "$VERBOSE_DEBUG" = "yes" ]; then
      echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
          "- forwarding $IF_TUNNEL => $IF_INT enabled"
  fi
fi

#setup port forwarding
#if [ "$PORT_FW" = 'enabled' ] && [ "$FORWARD_PORT_ENABLED" = 'yes' ]; then
#	iptables -A PREROUTING -t nat -p tcp --dport $TUN_PORT -j DNAT --to "$FORWARD_IP"
#	iptables -A PREROUTING -t nat -p udp --dport $TUN_PORT -j DNAT --to "$FORWARD_IP"
#	iptables -A FORWARD -i $IF_TUNNEL -p tcp --dport $TUN_PORT -d "$FORWARD_IP" -j ACCEPT
#	iptables -A FORWARD -i $IF_TUNNEL -p udp --dport $TUN_PORT -d "$FORWARD_IP" -j ACCEPT
#	if [ "$VERBOSE_DEBUG" = "yes" ]; then
#		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
#			"- port forwaring $IF_TUNNEL => '$FORWARD_IP':$TUN_PORT enabled"
#	fi
#else
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- port forwaring $IF_TUNNEL => '$FORWARD_IP' has NOT been enabled"
	fi
#fi

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

#allowing SNMP traffic
if [ ! -z "${FIREWALL_IF_SNMP[0]}" ]; then
  for interface in "${FIREWALL_IF_SNMP[@]}"
  do
    echo "pass in on $interface proto udp from any to any port 161 keep state" >> "$fwfile"
    echo "pass out on $interface proto udp from any to any port 162 keep state" >> "$fwfile"
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- SNMP enabled for interface: $interface"
	fi
  done
fi

#allowing Secure SNMP traffic
if [ ! -z "${FIREWALL_IF_SECSNMP[0]}" ]; then
  for interface in "${FIREWALL_IF_SECSNMP[@]}"
  do
    echo "pass in on $interface proto udp from any to any port 10161 keep state" >> "$fwfile"
    echo "pass out on $interface proto udp from any to any port 10162 keep state" >> "$fwfile"
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- Secure SNMP enabled for interface: $interface"
	fi
  done
fi


#allowing incoming SOCKS traffic
if [ "$SOCKS_INT_ENABLED" = 'yes' ]; then
    INT_IP=`/sbin/ifconfig "$IF_INT" | /usr/bin/grep -w "inet" | /usr/local/bin/gawk -F" " '{print $2}' | /usr/local/cut -d/ -f1`

    echo "pass in on $IF_INT proto {tcp, udp} from any to any port $SOCKS_INT_PORT keep state" >> "$fwfile"
    echo "pass out on $IF_TUNNEL proto {tcp, udp} from $INT_IP port 8080 to any state" >> "$fwfile"
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- SOCKS enabled for interface: $IF_INT"
	fi
fi
if [ "$SOCKS_EXT_ENABLED" = 'yes' ]; then
    echo "pass in on $IF_EXT proto {tcp, udp} from any to any port $SOCKS_EXT_PORT keep state" >> "$fwfile"
    echo "pass out on $IF_TUNNEL proto {tcp, udp} from $EXT_IP port 8080 to any state" >> "$fwfile"
	if [ "$VERBOSE_DEBUG" = "yes" ]; then
		echo -e "[deb ] "$(date +"%Y-%m-%d %H:%M:%S")\
			"- SOCKS enabled for interface: $IF_EXT"
	fi
fi

#allowing incoming traffic to web UI
if [ ! -z "${FIREWALL_IF_WEB[0]}" ]; then
  printf "\n#Allow webUI traffic\n" >> "$fwfile"

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



# setup default routes - 2>/dev/null needs to be fixed, check if exists first, then remove or keep
echo "E: route delete default dev $IF_EXT"
#route delete default dev $IF_EXT 2>/dev/null
echo "E: route add default gw $TUN_GATEWAY dev $IF_TUNNEL"
#route add default gw $TUN_GATEWAY dev $IF_TUNNEL 2>/dev/null

# Enable forwarding
sysctl net.inet.ip.forwarding=1
