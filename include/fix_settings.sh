#!/bin/bash
# support script for pia-setup
# this script will add missing settings to the config file
LANG=en_US.UTF-8
export LANG
source '/usr/local/pia/settings.conf'
source '/usr/local/pia/include/commands.sh'



#check if settings exist in settings.conf and add their default


if [ -z "${DAEMON_ENABLED}" ]; then
  echo 'DAEMON_ENABLED="no"' >> '/usr/local/pia/settings.conf'
  echo 'FAIL_ACTION="failover"' >> '/usr/local/pia/settings.conf'
  echo 'FAIL_RETRY_VPN=4' >> '/usr/local/pia/settings.conf'
  echo 'FAIL_RETRY_INTERNET=3' >> '/usr/local/pia/settings.conf'
  echo 'FAIL_RETRY_DELAY=1' >> '/usr/local/pia/settings.conf'
fi

if [ ! ${NAMESERVERS[0]+abc} ]; then
  echo '# list of VPN connections to use, the first is always the primary' >> '/usr/local/pia/settings.conf'
  echo 'MYVPN[0]="PIAtcp/CA Toronto"' >> '/usr/local/pia/settings.conf'
  echo 'MYVPN[1]="PIAtcp/Switzerland"' >> '/usr/local/pia/settings.conf'
  echo 'MYVPN[2]="PIAtcp/Sweden"' >> '/usr/local/pia/settings.conf'
fi

if [ -z "${SLEEP_INTERNET_DOWN}" ]; then
  echo 'SLEEP_INTERNET_DOWN=320' >> '/usr/local/pia/settings.conf'
  echo 'SLEEP_PING_RETEST=4' >> '/usr/local/pia/settings.conf'
  echo 'SLEEP_RECONNECT_ERROR=320' >> '/usr/local/pia/settings.conf'
  echo 'SLEEP_MAIN_LOOP=30' >> '/usr/local/pia/settings.conf'
fi

if [ -z "${VERBOSE}" ]; then
  echo 'VERBOSE="no"' >> '/usr/local/pia/settings.conf'
  echo 'VERBOSE_DEBUG="no"' >> '/usr/local/pia/settings.conf'

fi

ret=`$CMD_GREP -c 'bold=' /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'bold=`tput bold`' >> '/usr/local/pia/settings.conf'
  echo 'normal=`tput sgr0`' >> '/usr/local/pia/settings.conf'
fi

if [ -z "${FORWARD_IP}" ]; then
  echo 'FORWARD_IP="192.168.10.101"' >> '/usr/local/pia/settings.conf'
fi

if [ -z "${FORWARD_PORT_ENABLED}" ]; then
  echo 'FORWARD_PORT_ENABLED="yes"' >> '/usr/local/pia/settings.conf'
fi

if [ -z "${DAEMON_ENABLED}" ]; then
  echo 'DAEMON_ENABLED="no"' >> '/usr/local/pia/settings.conf'
fi

if [ -z "${FORWARD_VM_LAN}" ]; then
  echo 'FORWARD_VM_LAN="yes"' >> '/usr/local/pia/settings.conf'
fi

if [ -z "${FORWARD_PUBLIC_LAN}" ]; then
  echo 'FORWARD_PUBLIC_LAN="no"' >> '/usr/local/pia/settings.conf'
fi

if [ ! ${FIREWALL_IF_SSH[0]+abc} ]; then
  echo 'FIREWALL_IF_SSH[0]=""' >> '/usr/local/pia/settings.conf'
fi

if [ ! ${FIREWALL_IF_WEB[0]+abc} ]; then
  echo 'FIREWALL_IF_WEB[0]="em0"' >> '/usr/local/pia/settings.conf'
  echo 'FIREWALL_IF_WEB[1]="em1"' >> '/usr/local/pia/settings.conf'
fi

if [ ! ${NAMESERVERS[0]+abc} ]; then
  echo 'NAMESERVERS[0]="8.8.8.8"' >> '/usr/local/pia/settings.conf'
  echo 'NAMESERVERS[1]="208.67.222.222"' >> '/usr/local/pia/settings.conf'
  echo 'NAMESERVERS[2]="8.8.4.4"' >> '/usr/local/pia/settings.conf'
  echo 'NAMESERVERS[3]="208.67.220.220"' >> '/usr/local/pia/settings.conf'
fi

if [ -z "${IF_ETH0_DHCP}" ]; then
  echo 'IF_ETH0_DHCP="yes"' >> '/usr/local/pia/settings.conf'
  echo 'IF_ETH0_IP=""' >> '/usr/local/pia/settings.conf'
  echo 'IF_ETH0_SUB=""' >> '/usr/local/pia/settings.conf'
  echo 'IF_ETH0_GW=""' >> '/usr/local/pia/settings.conf'
fi

if [ -z "${IF_ETH1_DHCP}" ]; then
  echo 'IF_ETH1_DHCP="no"' >> '/usr/local/pia/settings.conf'
  echo 'IF_ETH1_IP="192.168.10.1"' >> '/usr/local/pia/settings.conf'
  echo 'IF_ETH1_SUB="255.255.255.0"' >> '/usr/local/pia/settings.conf'
  echo 'IF_ETH1_GW=""' >> '/usr/local/pia/settings.conf'
fi

if [ -z "${DHCPD_ENABLED1}" ]; then
  echo 'DHCPD_ENABLED1="yes"' >> '/usr/local/pia/settings.conf'
  echo 'DHCPD_RANGE1="192.168.10.101 192.168.10.151"' >> '/usr/local/pia/settings.conf'
  echo 'DHCPD_BROADCAST1="192.168.10.255"' >> '/usr/local/pia/settings.conf'
  echo 'DHCPD_ROUTER1="192.168.10.1"' >> '/usr/local/pia/settings.conf'
  echo 'DHCPD_SUBNET1="192.168.10.0"' >> '/usr/local/pia/settings.conf'
  echo 'DHCPD_MASK1="255.255.255.0"' >> '/usr/local/pia/settings.conf'
fi

if [ -z "${DHCPD_ENABLED2}" ]; then
  echo 'DHCPD_ENABLED2="no"' >> '/usr/local/pia/settings.conf'
  echo 'DHCPD_RANGE2=""' >> '/usr/local/pia/settings.conf'
  echo 'DHCPD_BROADCAST2=""' >> '/usr/local/pia/settings.conf'
  echo 'DHCPD_ROUTER2=""' >> '/usr/local/pia/settings.conf'
  echo 'DHCPD_SUBNET2=""' >> '/usr/local/pia/settings.conf'
  echo 'DHCPD_MASK2=""' >> '/usr/local/pia/settings.conf'
fi




# in BASH DHCPD_STATIC_MAC="" then -z fails
ret=`$CMD_GREP -c "DHCPD_STATIC_MAC" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'DHCPD_STATIC_MAC=""' >> '/usr/local/pia/settings.conf'
fi
ret=`$CMD_GREP -c "DHCPD_STATIC_IP" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'DHCPD_STATIC_IP=""' >> '/usr/local/pia/settings.conf'
fi

#add web UI user info
ret=`$CMD_GREP -c "WEB_UI_USER" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'WEB_UI_USER=""' >> '/usr/local/pia/settings.conf'
fi
ret=`$CMD_GREP -c "WEB_UI_PASSWORD" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'WEB_UI_PASSWORD=""' >> '/usr/local/pia/settings.conf'
fi
ret=`$CMD_GREP -c "WEB_UI_NAMESPACE" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'WEB_UI_NAMESPACE="3DApa2ezdm"' >> '/usr/local/pia/settings.conf'
fi
ret=`$CMD_GREP -c "WEB_UI_COOKIE" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'WEB_UI_COOKIE=""' >> '/usr/local/pia/settings.conf'
fi
ret=`$CMD_GREP -c "WEB_UI_COOKIE_LIFETIME" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'WEB_UI_COOKIE_LIFETIME="120"' >> '/usr/local/pia/settings.conf'
fi

#ping failure
ret=`$CMD_GREP -c "PING_MAX_LOSS" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'PING_MAX_LOSS="20"' >> '/usr/local/pia/settings.conf'
fi

#SOCKS proxy failure
ret=`$CMD_GREP -c "SOCKS_EXT_ENABLED" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'SOCKS_EXT_ENABLED="no"' >> '/usr/local/pia/settings.conf'
  echo 'SOCKS_EXT_PORT="8080"' >> '/usr/local/pia/settings.conf'
  echo 'SOCKS_EXT_FROM="0.0.0.0/0"' >> '/usr/local/pia/settings.conf'
  echo 'SOCKS_EXT_TO="0.0.0.0/0"' >> '/usr/local/pia/settings.conf'
  echo 'SOCKS_EXT_FROM_PORTRANGE="1-65535"' >> '/usr/local/pia/settings.conf'

  echo 'SOCKS_INT_ENABLED="no"' >> '/usr/local/pia/settings.conf'
  echo 'SOCKS_INT_PORT="8080"' >> '/usr/local/pia/settings.conf'
  echo 'SOCKS_INT_FROM="0.0.0.0/0"' >> '/usr/local/pia/settings.conf'
  echo 'SOCKS_INT_TO="0.0.0.0/0"' >> '/usr/local/pia/settings.conf'
  echo 'SOCKS_INT_FROM_PORTRANGE="1-65535"' >> '/usr/local/pia/settings.conf'
fi

# git branch setting for online updates
ret=`$CMD_GREP -c "GIT_BRANCH" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  CURRENT_BRANCH=`cd /usr/local/pia/ ; $CMD_GIT branch | $CMD_GREP '*' | $CMD_GAWK -F" " '{print $2}'`
  if [ "$CURRENT_BRANCH" != "" ]; then
    echo "using ${CURRENT_BRANCH}"
    echo "GIT_BRANCH='${CURRENT_BRANCH}'" >> '/usr/local/pia/settings.conf'
  else
    echo "default to stable branch"
    echo 'GIT_BRANCH="release_php-gui"' >> '/usr/local/pia/settings.conf'
  fi
else
  echo "branch already set"
fi


# initial setup of phpDCHPD
ret=`$CMD_GREP -c "GIT_SUB_PDHCPD" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  cd /usr/local/pia/
  $CMD_GIT submodule init htdocs/plugin/phpdhcpd
  $CMD_GIT submodule update htdocs/plugin/phpdhcpd
  echo 'GIT_SUB_PDHCPD="ran setup"' >> '/usr/local/pia/settings.conf'
fi

# initial setup of Parsedown
ret=`$CMD_GREP -c "GIT_SUB_PARSEDOWN" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  cd /usr/local/pia/
  $CMD_GIT submodule init htdocs/plugin/parsedown
  $CMD_GIT submodule update htdocs/plugin/parsedown
  echo 'GIT_SUB_PARSEDOWN="ran setup"' >> '/usr/local/pia/settings.conf'
fi


# webUI Overview refresh interval
ret=`$CMD_GREP -c "WEB_UI_REFRESH_TIME" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'WEB_UI_REFRESH_TIME="15000"' >> '/usr/local/pia/settings.conf'
fi



# new setting to enable or disable VPN providers
if [ ! ${VPN_PROVIDERS[0]+abc} ]; then
  echo 'VPN_PROVIDERS[0]="PIAtcp"' >> '/usr/local/pia/settings.conf'
  echo 'VPN_PROVIDERS[1]="FrootVPN"' >> '/usr/local/pia/settings.conf'
fi


# add setup wizard value to settings
ret=`$CMD_GREP -c "SETUP_WIZARD_COMPLETED" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'SETUP_WIZARD_COMPLETED="no"' >> '/usr/local/pia/settings.conf'
fi


# move to new format. this can be removed after a few months - added Mai 2015
if [ -f /usr/local/pia/login-frootvpn.conf ] && [ ! -f /usr/local/pia/login-FrootVPN.conf ]; then
  mv /usr/local/pia/login-frootvpn.conf /usr/local/pia/login-FrootVPN.conf
fi


if [ ! ${FIREWALL_IF_SNMP[0]+abc} ]; then
  echo 'FIREWALL_IF_SNMP[0]=""' >> '/usr/local/pia/settings.conf'
fi
if [ ! ${FIREWALL_IF_SECSNMP[0]+abc} ]; then
  echo 'FIREWALL_IF_SECSNMP[0]=""' >> '/usr/local/pia/settings.conf'
fi


ret=`$CMD_GREP -c "TRANSMISSION_ENABLED" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  echo 'TRANSMISSION_ENABLED="no"' >> '/usr/local/pia/settings.conf'
  echo 'CIFS_AUTO="no"' >> '/usr/local/pia/settings.conf'
  echo 'CIFS_SHARE=""' >> '/usr/local/pia/settings.conf'
  echo 'CIFS_USER=""' >> '/usr/local/pia/settings.conf'
  echo 'CIFS_PASSWORD=""' >> '/usr/local/pia/settings.conf'
  echo 'CIFS_MOUNT=""' >> '/usr/local/pia/settings.conf'
  echo 'CIFS_INTERFACE="any"' >> '/usr/local/pia/settings.conf'
  echo 'TRANSMISSION_WHITELIST="127.0.0.1"' >> '/usr/local/pia/settings.conf'
  echo 'TRANSMISSION_USER="transmission"' >> '/usr/local/pia/settings.conf'
  echo 'TRANSMISSION_PASSWORD="transmission"' >> '/usr/local/pia/settings.conf'
  echo 'TRANSMISSION_AUTH_REQUIRED="true"' >> '/usr/local/pia/settings.conf'
fi

# add support for custom firewall ports
if [ ! ${FIREWALL_INT[0]+abc} ]; then
  echo 'FIREWALL_INT[0]=""' >> '/usr/local/pia/settings.conf'
fi
if [ ! ${FIREWALL_EXT[0]+abc} ]; then
  echo 'FIREWALL_EXT[0]=""' >> '/usr/local/pia/settings.conf'
fi

# added fix for eth0 not getting an IP quick enough during boot for pia-status to display the IP
grep "allow-hotplug eth0" /etc/network/interfaces &> /dev/null  && /usr/local/pia/include/network-interfaces.sh

grep "IF_DEFAULTROUTER" /usr/local/pia/settings.conf &> /dev/null || echo 'IF_DEFAULTROUTER=""' >> /usr/local/pia/settings.conf


ret=$(ps -p 1 | grep -c systemd )
if [ "$ret" -eq 1 ] && [ ! -L "/etc/systemd/system/pia-autostart.service" ]; then
	# system running systemD
	systemctl enable  /usr/local/pia/include/service/pia-autostart.service
	systemctl disable  /usr/local/pia/include/service/pia-daemon.service
    systemctl enable  /usr/local/pia/include/service/pia-firstboot.service

    # systemD is a pile of shit - now using if-up.d since systemD is not consistent
    #systemctl enable  /usr/local/pia/include/service/pia-boot-msg.service
    if [ ! -L '/etc/network/if-up.d/piamessage' ] && [ ! -f '/etc/network/if-up.d/piamessage' ]; then
      ln -s /usr/local/pia/include/service/pia-boot-msg.sh  /etc/network/if-up.d/piamessage
    fi

elif [ "$ret" -eq 0 ] && [ ! -L "/etc/systemd/system/pia-boot-msg.service" ]; then
	echo "do to";
fi

ret=`$CMD_GREP -c "HTDOCS_PATH" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
    unamestr=`uname`

    if [ "$unamestr" = "Linux" ]; then
        OS_TYPE="Linux"
        HTDOCS_PATH="/var/www/html"
        APACHE_USER="www-data"
        echo 'OS_TYPE="Linux"' >> '/usr/local/pia/settings.conf'
        echo 'HTDOCS_PATH="/var/www/html"' >> '/usr/local/pia/settings.conf'
        echo 'APACHE_USER="www-data"' >> '/usr/local/pia/settings.conf'
    else
        OS_TYPE="FreeBSD"
        HTDOCS_PATH="/usr/local/www/apache24/data"
        APACHE_USER="www"
        echo 'OS_TYPE="FreeBSD"' >> '/usr/local/pia/settings.conf'
        echo 'HTDOCS_PATH="/usr/local/www/apache24/data"' >> '/usr/local/pia/settings.conf'
        echo 'APACHE_USER="www"' >> '/usr/local/pia/settings.conf'
    fi

fi



# SOCKS5 software selection
ret=`$CMD_GREP -c "SOCKS_SERVER_TYPE" /usr/local/pia/settings.conf`
if [ $ret = 0 ]; then
  if [ "$OS_TYPE" = "Linux" ]; then
    echo 'SOCKS_SERVER_TYPE="dante"' >> '/usr/local/pia/settings.conf'
  else
    echo 'SOCKS_SERVER_TYPE="3proxy"' >> '/usr/local/pia/settings.conf'
  fi
fi