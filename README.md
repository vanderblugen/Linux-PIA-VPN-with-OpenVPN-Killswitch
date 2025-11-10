# PIA VPN with Killswitch
To manually set it up view do-it-yourself.md

# Pre-Installation
Run this prior to either of the scripts

```shell

apt-get update -y
apt-get upgrade -y

if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 
   exit 1
fi

if [ ! -f "/usr/sbin/ifconfig" ]; then
   sudo apt install net-tools -y
fi

apt-get install ifupdown -y
apt-get install openvpn -y

# pre download iptables-persistent
apt-get install --download-only iptables-persistent -y
sudo apt-get install openvpn-systemd-resolved -y
```

Two scripts available
- ip_tables.sh
    - This is a shell script that fully installs PIA VPN using OpenVPN and sets up a killswitch using shell
- ip_tables.php
    - This is a newer script that fully installs with no user interaction using php and outputs any logs to a file
- Rules are added to allow for local LAN traffic
- Both scripts need to be run as sudo/root

## Variables
- Multiple variables are present in the scripts that may require updating

#### Ports Left Open
- Different OVPN files may require different port numbers
- Consult the specific OVPN file for verification

#### Downloaded ZIP File
- For some more information on the PIA OVPN files:  https://www.privateinternetaccess.com/helpdesk/kb/articles/where-can-i-find-your-ovpn-files-2

## Disclaimer
- This is free for personal use and comes with absolutely no warranty. You use this program entirely at your own risk. The author will not be liable for any damages arising from the use of this program. This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even an implied warranty for a particular purpose

## Backup Your Stuff Before Running This
- Before running this script make sure that you have what you need backed up
- The noted scripts make changes to several files and may cause loss in internet connectivity

## To Get These
- wget https://raw.githubusercontent.com/vanderblugen/Linux-PIA-VPN-with-OpenVPN-Killswitch/master/ip_tables.sh
- wget https://raw.githubusercontent.com/vanderblugen/Linux-PIA-VPN-with-OpenVPN-Killswitch/master/ip_tables.php

## To Check for DNS Leaks

```shell
cd
curl https://raw.githubusercontent.com/macvk/dnsleaktest/master/dnsleaktest.sh -o dnsleaktest.sh
chmod +x dnsleaktest.sh
./dnsleaktest.sh
```

## If anyone wants to contribute please reach out
