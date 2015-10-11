#!/bin/bash
# FreeBSD specific functions
LANG=en_US.UTF-8
export LANG

#return variables and static stuff
RET_PING_HOST=""
RET_FILE_IS_WRITABLE="no"
declare -a RET_MODIFIED_ARRAY
RET_FORWARD_PORT="FALSE"
RET_FORWARD_STATE="FALSE"
RET_GET_PACKET_LOSS=""
RET_CHK_IF_TUN0="down"
RET_CHK_OPENVPN="down"


# checks if tun0 is up
# @return RET_CHK_IF_TUN0 string "up" if up, "down" if down
function check_tun0_up() {

  /sbin/ifconfig $IF_TUNNEL &> /dev/null
  if [ $? -eq 0 ]; then
    RET_CHK_IF_TUN0="up"
  else
    RET_CHK_IF_TUN0="down"
  fi
}

# checks if openvpn is running
# @return RET_CHK_OPENVPN string "running" if running, "down" if down, "multi" if more then one running
function check_openvpn_running() {

  RET=`ps aux | /usr/bin/grep "openvpn /usr/local/pia" 2> /dev/null | /usr/bin/grep -c -v "grep"`
  if [ "$RET" -eq "1" ]; then
    RET_CHK_OPENVPN="running"
  elif [ "$RET" -gt "1" ]; then
    RET_CHK_OPENVPN="multi"
  else
    RET_CHK_OPENVPN="down"
  fi
}


# kills openvpn, sets firewall to stop and anything else
# that needs to be done to lock everything back down
function VPNstop() {

  killall openvpn &> /dev/null

  # ensue it has ended
  while true; do
    check_openvpn_running
    if [ "$RET_CHK_OPENVPN" = "down" ]; then
      break
    fi
  done

  echo "need to add firewall stop scripts once they are done"
}