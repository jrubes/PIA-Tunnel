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







