This is a verified setup for PIA VPN setup on a Raspberry Pi 3b+ with a killswitch on the VPN.  So if the VPN goes down, the internet doesn't work.

This setup starts with 2021-01-11-raspios-buster-armhf-lite.  The version with desktop is a little easier to setup due to the easability of using a https://dnsleaktest.com to verify that everything is operating as expected.

Make sure that your running stuff as root
```shell
sudo su
```


Update
```shell
apt update
apt upgrade -y 
apt full-upgrade -y 
```


Check the current public IP and write it down
```shell
curl icanhazip.com
```


Install, start, and enable resolvconf start on reboot
```shell
apt install resolvconf -y
systemctl start resolvconf.service
systemctl enable resolvconf.service
```


Install openvpn and put the files from PIA and put into place
```shell
apt install openvpn -y
wget https://www.privateinternetaccess.com/openvpn/openvpn-strong.zip
unzip openvpn-strong.zip -d /etc/openvpn
rm openvpn-strong.zip
cd /etc/openvpn
```


Look at the files and figure out what location you are going to be using
```shell
ls
```


In this example, italy is going to be used, substitute in the appropriate file name
Copy that file to the name vpn.conf
```shell
cp italy.ovpn vpn.conf
```


Create a login file.  There's multiple ways to do this.  USERNAME and PASSWORD are your login credentials for the VPN.
```shell
echo USERNAME >> /etc/openvpn/login
echo PASSWORD >> /etc/openvpn/login
chmod og-rx,u+x,a-w login
```


Add the login info to the vpn.conf file
```shell
sed -i 's/auth-user-pass/auth-user-pass \/etc\/openvpn\/login/g' vpn.conf
```


Help to prevent DNS leaks
```shell
echo "script-security 2" >> /etc/openvpn/vpn.conf
echo "up /etc/openvpn/update-resolv-conf" >> /etc/openvpn/vpn.conf
echo "down /etc/openvpn/update-resolv-conf" >> /etc/openvpn/vpn.conf
```


Enable openvpn to start on reboot and start it
```shell
systemctl enable openvpn@vpn
reboot now
```


Establish root after reboot
```shell
sudo su
```


Make sure that vpn is working before proceeding by rechecking the IP address
```shell
curl icanhazip.com
```


Before proceeding note your network address, generally written similar to 192.168.0.0/24
It is displayed with other information in this.
```shell
ip route
```


In case there's anything else in the iptables, clear those out
```shell
iptables -F
iptables -t nat -F
iptables -t mangle -F
iptables -X
```


Allow loopback device (internal communication)
```shell
iptables -A INPUT -i lo -j ACCEPT
iptables -A OUTPUT -o lo -j ACCEPT
```


Allow all local network traffic
```shell
iptables -A INPUT -s 192.168.1.0/24 -j ACCEPT
iptables -A OUTPUT -d 192.168.1.0/24 -j ACCEPT
```


Verify the port needed in the vpn.conf file
It's located at the end of the line starting with [b]remote[/b]
```shell
nano /etc/openvpn/vpn.conf[/conf]

Allow VPN establishment with only 2 ports open, 1 for DNS {53) and 1 for VPN (1197)
If establishing thru an IP and not DNS, the ones with port 53 can be removed
Port 1197 may be different depending on the VPN.  Open the vpn.conf file to verify

```shell
iptables -A OUTPUT -p UDP --dport 53 -j ACCEPT
iptables -A INPUT -p UDP --sport 53 -j ACCEPT

iptables -A OUTPUT -p UDP --dport 1197 -j ACCEPT
iptables -A INPUT -p UDP --sport 1197 -j ACCEPT
```


Accept all TUN connections (tun = VPN tunnel)
```shell
iptables -A OUTPUT -o tun+ -j ACCEPT
iptables -A INPUT -i tun+ -j ACCEPT
```

Set default policies to drop all communication unless specifically allowed
```shell
iptables -P INPUT DROP
iptables -P OUTPUT DROP
iptables -P FORWARD DROP
```

Bring the network connection down then back up.
If remoted into the Pi, copy the extra line.
```shell
ifconfig eth0 down
ifconfig eth0 up

```

Stop and start openvpn 
```shell
service openvpn stop
service openvpn start

```

Setup persistent iptables to keep after reboot.
Click YES on both when prompted
```shell
apt install iptables-persistent -y
```

Save the iptables and enable to start after reboot
```shell
netfilter-persistent save
systemctl enable netfilter-persistent
```


If running from the command line, dnsleaktest can be run from the command line
Install this first
```shell
apt-get install jq -y
```


Download it and enable it to run as a script
```shell
wget https://raw.githubusercontent.com/macvk/dnsleaktest/master/dnsleaktest.sh
chmod +x dnsleaktest.sh
```


Run it
```shell
./dnsleaktest.sh
```
this will say if dns is leaking and where you are appearing from


If running desktop.  Open the browser to dnsleaktest.com and run extended test


Test if the killswitch is working.
Note that you can ping google.com.  Ctrl+C to stop
```shell
ping google.com
```


Disable openvpn to see if things are stil working
```shell
service openvpn stop
```


Now ping google.com again
```shell
ping google.com
```


No ping is good

Turn openvpn back on and your good to go
```shell
service openvpn start
```


Here's a few of my sources:

https://www.novaspirit.com/2017/06/22/raspberry-pi-vpn-router-w-pia/

https://www.raspberrypi.org/forums/viewtopic.php?t=43375
