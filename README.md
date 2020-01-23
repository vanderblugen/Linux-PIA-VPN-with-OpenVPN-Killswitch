# PIA VPN with Killswitch

The pvwk.sh script installs PIA VPN using OpenVPN and sets up a killswitch.  
Rules are added to allow for local LAN traffic.  
The script needs to be run as root/sudo

## Variables
There are variables that need to be updated in script

#### PIA_USERNAME and PIA_PASSWORD
These are your PIA credentials
    
#### NAMESERVER_1/NAMESERVER_2
These may need to be updated at some point, but currently are correct.

#### NETWORK_ADDRESS
This is the address of the network, not the IP address of a machine on the network.  
Don't forget to put the / number as well

#### NETWORK_INTERFACE_NAME
This is the name of the interface that faces the internet on the machine.  
This can be determined by running ifconfig from the terminal

#### PORTS LEFT OPEN
These are currently correct but may change wanting to implement this with a different VPN provider.

#### FILENAME
This is the filename of the location where connection should end on the internet.
Filenames are given in the script and are currently:

AU Melbourne.ovpn, AU Perth.ovpn, Austria.ovpn, AU Sydney.ovpn, Belgium.ovpn, CA Montreal.ovpn, CA Toronto.ovpn, CA Vancouver.ovpn, Czech Republic.ovpn, DE Berlin.ovpn, DE Frankfurt.ovpn, Finland.ovpn, France.ovpn, Hong Kong.ovpn, Hungary.ovpn, India.ovpn, Ireland.ovpn, Israel.ovpn, Italy.ovpn, Japan.ovpn, Luxembourg.ovpn, Mexico.ovpn, Netherlands.ovpn, New Zealand.ovpn, Norway.ovpn, Poland.ovpn, Romania.ovpn, Singapore.ovpn, Spain.ovpn, Sweden.ovpn, Switzerland.ovpn, UAE.ovpn, UK London.ovpn, UK Manchester.ovpn, UK Southampton.ovpn, US Atlanta.ovpn, US California.ovpn, US Chicago.ovpn, US Denver.ovpn, US East.ovpn, US Florida.ovpn, US Houston.ovpn, US Las Vegas.ovpn, US New York City.ovpn, US Seattle.ovpn, US Silicon Valley.ovpn, US Texas.ovpn, US Washington DC.ovpn, US West.ovpn

## WHEN IPTABLES-PERSISTENT INSTALLS
Select Yes for IPv4 and Yes for IPv6

## DISCLAIMER
This program is free for personal and commercial use and comes with absolutely no warranty. You use this program entirely at your own risk. The author will not be liable for any damages arising from the use of this program. This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even an implied warranty for a particular purpose.

# BACKUP YOUR STUFF BEFORE RUNNING THIS
Before running this script make sure that you have what you need backed up.
It makes changes to several files and may cause loss in internet connection if errors generate during the installation.

# TO GET THIS 
wget https://raw.githubusercontent.com/vanderblugen/pvwk/master/pvwk.sh


## If anyone wants to contribute please reach out
