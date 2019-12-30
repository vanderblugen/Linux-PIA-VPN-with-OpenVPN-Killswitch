#!/bin/bash

#############################################################################
##### This script installs PIA VPN using OpenVPN and sets up a killswitch
##### This script needs to be run as root
#############################################################################

#############################################################################
################################  VARIABLES  ################################
#############################  UPDATE AS NEEDED  ############################
#############################################################################

# PIA credentials
PIA_USERNAME="USERNAME"
PIA_PASSWORD="PASSWORD"

# Nameservers may change, set to the defaults, update to new IP if necessary
NAME_SERVER1="209.222.18.222"
NAME_SERVER2="209.222.18.218"

# Address of the Network (not the machine IP but of the network) with the / number as well
NETWORK_ADDRESS="192.168.1.0/24"

# Network interface name
NETWORK_INTERFACE_NAME="enp0s3"

# UDP Ports that are left open
# Default ports are 53 for DNS and 1198 for VPN which are both UDP
# Ports may change or differ

PORT1_NUMBER="53"
PORT1_TYPE="UDP"

PORT2_NUMBER="1198"
PORT2_TYPE="UDP"

# Name of country filename.
FILENAME="CA Toronto.ovpn"

# As of the writing the filenames were:
# AU Melbourne.ovpn, AU Perth.ovpn, Austria.ovpn, AU Sydney.ovpn, Belgium.ovpn,
# CA Montreal.ovpn, CA Toronto.ovpn, CA Vancouver.ovpn, Czech Republic.ovpn,
# DE Berlin.ovpn, DE Frankfurt.ovpn, Finland.ovpn, France.ovpn, Hong Kong.ovpn,
# Hungary.ovpn, India.ovpn, Ireland.ovpn, Israel.ovpn, Italy.ovpn, Japan.ovpn,
# Luxembourg.ovpn, Mexico.ovpn, Netherlands.ovpn, New Zealand.ovpn, Norway.ovpn,
# Poland.ovpn, Romania.ovpn, Singapore.ovpn, Spain.ovpn, Sweden.ovpn, Switzerland.ovpn,
# UAE.ovpn, UK London.ovpn, UK Manchester.ovpn, UK Southampton.ovpn, US Atlanta.ovpn,
# US California.ovpn, US Chicago.ovpn, US Denver.ovpn, US East.ovpn, US Florida.ovpn,
# US Houston.ovpn, US Las Vegas.ovpn, US New York City.ovpn, US Seattle.ovpn,
# US Silicon Valley.ovpn, US Texas.ovpn, US Washington DC.ovpn, US West.ovpn

#############################################################################
##############################  SCRIPT ITSELF  ##############################
#############################################################################.

if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 
   exit 1
fi

apt-get install ifupdown -y
apt-get install -f resolvconf -y

echo "nameserver $NAME_SERVER1" >> /etc/resolvconf/resolv.conf.d/head
echo "nameserver $NAME_SERVER2" >> /etc/resolvconf/resolv.conf.d/head

systemctl start resolvconf.service
#systemctl start resolvconf-pull-resolved
systemctl enable resolvconf.service

apt-get install unzip -y
apt-get install openvpn -y
wget https://www.privateinternetaccess.com/openvpn/openvpn.zip
unzip openvpn.zip -d /etc/openvpn
rm openvpn.zip
cd /etc/openvpn

echo $PIA_USERNAME >> /etc/openvpn/login
echo $PIA_PASSWORD >> /etc/openvpn/login
chmod og-rx,u+x,a-w login
cp "$FILENAME" vpn.conf
sed -i 's/auth-user-pass/auth-user-pass \/etc\/openvpn\/login/g' vpn.conf
systemctl enable openvpn@vpn

# This killswitch is based off of a portion of https://www.novaspirit.com/2017/06/22/raspberry-pi-vpn-router-w-pia/

# Clear out the old rules
iptables -F
iptables -t nat -F
iptables -t mangle -F
iptables -X

# Allow loopback device (internal communication)
iptables -A INPUT -i lo -j ACCEPT
iptables -A OUTPUT -o lo -j ACCEPT

#Allow all local traffic.
iptables -A INPUT -s $NETWORK_ADDRESS -j ACCEPT
iptables -A OUTPUT -d $NETWORK_ADDRESS -j ACCEPT

# Allow VPN establishment with only 2 ports open, 1 for DNS and 1 for VPN
# If establishing thru an IP and not DNS, the ones with port 53 can be removed
# Port 1198 may be different depending on the VPN

iptables -A OUTPUT -p $PORT1_TYPE --dport $PORT1_NUMBER -j ACCEPT
iptables -A INPUT -p $PORT1_TYPE --sport $PORT1_NUMBER -j ACCEPT
iptables -A OUTPUT -p $PORT2_TYPE --dport $PORT2_NUMBER -j ACCEPT
iptables -A INPUT -p $PORT2_TYPE --sport $PORT2_NUMBER -j ACCEPT

#Accept all TUN connections (tun = VPN tunnel)
iptables -A OUTPUT -o tun+ -j ACCEPT
iptables -A INPUT -i tun+ -j ACCEPT

#Set default policies to drop all communication unless specifically allowed
iptables -P INPUT DROP
iptables -P OUTPUT DROP
iptables -P FORWARD DROP

ifconfig $NETWORK_INTERFACE_NAME down
ifconfig $NETWORK_INTERFACE_NAME up

service openvpn stop
sleep 5
service openvpn start
sleep 5

# If the script has any problems this is where it is.  This portion may need to be run twice. 

apt-get install iptables-persistent -y
netfilter-persistent save
systemctl enable netfilter-persistent

#############################################################################
################################# OPTIONAL ##################################
#############################################################################
# just take out the # before to run this part

# apt-get install jq -y
# wget https://raw.githubusercontent.com/macvk/dnsleaktest/master/dnsleaktest.sh
# chmod +x dnsleaktest.sh
# ./dnsleaktest.sh
# this will say if dns is leaking and where you are appearing from

# Can also be tested by going to dnsleaktest.com and running extended test
#############################################################################
#################################  ENJOY  ###################################
#############################################################################
