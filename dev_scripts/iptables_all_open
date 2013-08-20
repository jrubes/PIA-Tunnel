# iptables commands to open INPUT and OUTPUT all the way
# I use this to simulate disconnects.

iptables -F
iptables -t nat -F
iptables -t nat -X
iptables -t mangle -F
iptables -t mangle -X
iptables -P INPUT ACCEPT
iptables -P FORWARD DROP
iptables -P OUTPUT ACCEPT


iptables -L