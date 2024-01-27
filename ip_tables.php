<?php

// This script requires preinstallation of php 5.2.0 or higher prior to running
// This script installs PIA VPN using OpenVPN and sets up a killswitch using iptables
// This script needs to be run as root.

###################################################################################################################################
############################################################ VARIABLES ############################################################
###################################################################################################################################

$PiaUsername = "username";                                                           // PIA Username
$PiaPassword = "password";                                                           // PIA Password

// Name of the network interface
$NetworkInterfaceName = "enp0s3";                                                    // Network interface name

// UDP Ports that are left open.  Default ports are 53 for DNS and 1197 for VPN which are both UDP.  Ports may differ
$Port1Number="53";
$Port1Type="UDP";
$Port2Number="1197";
$Port2Type="UDP";

$OpenVpnLocation = "/etc/openvpn/";                                                  // Location where the openvpn files are going to be located
$LocalZipFile = "openvpn-strong.zip";                                                // Local zip file name
$url = "https://www.privateinternetaccess.com/openvpn/openvpn-strong.zip";           // Online zip file URL
$LocationName="ca_toronto.ovpn";                                                     // Openvpn filename for the country

$VpnConfigFilename = "vpn.conf";                                                      //VPN Config filename
$CredentialFilename = "login";                                                        // Login credentials stored in this file

$OldPattern = "auth-user-pass";
$NewPattern = "auth-user-pass ${OpenVpnLocation}${CredentialFilename}";

//Helps to ensure no DNS leaks
$DataAppend1 = "script-security 2" . "\nup ${OpenVpnLocation}update-resolv-conf" . "\ndown ${OpenVpnLocation}update-resolv-conf";

###################################################################################################################################
###################################################################################################################################
###################################################################################################################################

// Verify if running as root
if (posix_getuid() !== 0){
        exit("Ending script.  Not running as root.\n");
    };

// Run updates
echo "\n\n[Running Update]\n";
echo shell_exec('apt-get update -y');

echo "\n\n[Running Upgrade]\n";
echo shell_exec('apt-get upgrade -y');

// Install needed software
echo "\n\n[Installing php-zip]\n";
echo shell_exec('apt-get install php-zip -y');

echo "\n\n[Installing net-tools]\n";
echo shell_exec('apt-get install net-tools -y');

echo "\n\n[Installing ifupdown]\n";
echo shell_exec('apt-get install ifupdown -y');

echo "\n\n[Installing openvpn]\n";
echo shell_exec('apt-get install openvpn -y');

echo "\n\n[Installing resolvconf]\n";
echo shell_exec('apt-get install -f resolvconf -y');

echo "\n\n[PreDownloading iptables-persistent]\n";
echo shell_exec('apt-get install --download-only iptables-persistent -y');

// Get VPN files
$output = file_get_contents($url);
file_put_contents($LocalZipFile, $output);

// Start and Enable resolvconf service to start on boot

echo "\n\n[Starting resolvconf.service']\n";
echo shell_exec("systemctl start resolvconf.service");

echo "\n\n[Setting resolvconf.service to run on startup']\n";
echo shell_exec("systemctl enable resolvconf.service");

// Extract ZipFile
$output = new ZipArchive;
if ($output->open($LocalZipFile) === TRUE) {
    echo "\n\n[Unzipping file $LocalZipFile into $OpenVpnLocation]\n";
    $output->extractTo($OpenVpnLocation);
    $output->close();
} else {
    exit ("Exiting script. Unzip failed\n");
}

// Delete the zip file
echo "\n\n[Deleting Zip File $LocalZipFile]\n";
unlink($LocalZipFile);

// Change directory
echo "\n\n[Changing directory to $OpenVpnLocation]\n";
chdir($OpenVpnLocation);

// Create credential file
$output = fopen($CredentialFilename, "w");
if ($output) {
        echo "\n\n[Creating Credential File $CredentialFilename]\n";
        fwrite($output, $PiaUsername . PHP_EOL);
        fwrite($output, $PiaPassword . PHP_EOL);
        fclose($output);
} else {
    exit ("Exiting script. Unable to create $CredentialFilename\n");
}

// Update permissions on the credential file

echo "\n\n[Changing permissions on $CredentialFilename]\n";
chmod($CredentialFilename, 0500);

// Copy the openvpn file into the appropriate filename
echo "\n\n[Copying $LocationName to $VpnConfigFilename]\n";
copy($LocationName, $VpnConfigFilename);

// Update to have auth with login in the VpnConfigFilename
echo "\n\n[Updating $VpnConfigFilename with login file]\n";
$fileContent = file_get_contents($VpnConfigFilename);
$newFileContent = str_replace($OldPattern, $NewPattern, $fileContent);
file_put_contents($VpnConfigFilename, $newFileContent);

//Helps to ensure no DNS leaks
echo "\n\n[Updating $VpnConfigFilename to help ensure no DNS leaks]\n";
$myfile = file_put_contents($VpnConfigFilename, $DataAppend1.PHP_EOL , FILE_APPEND | LOCK_EX);

// enable openvpn with the config file to start automatically
$FileNameParts = pathinfo($VpnConfigFilename);
$VpnConfigFileBasename = $FileNameParts['filename']; // filename is only since PHP 5.2.0        //Baasename is the filename without the extension
echo "\n\n[Running 'systemctl enable openvpn@$VpnConfigFileBasename']\n";
echo shell_exec("systemctl enable openvpn@$VpnConfigFileBasename");

// Clearing out old IP tables rules
echo "\n\n[Clear out the old IP tables rules]\n";
echo shell_exec("iptables -F");
echo shell_exec("iptables -t nat -F");
echo shell_exec("iptables -t mangle -F");
echo shell_exec("iptables -X");

// Allow loopback device (internal communication)
echo "\n\n[Updating iptables to allow for loopback device (internal communication)]\n";
echo shell_exec("iptables -A INPUT -i lo -j ACCEPT");
echo shell_exec("iptables -A OUTPUT -o lo -j ACCEPT");

//Allow all local traffic.
echo "\n\n[Updating iptables to allow for all local traffic]\n";
echo shell_exec("iptables -A INPUT -s 10.0.0.0/8 -j ACCEPT");
echo shell_exec("iptables -A OUTPUT -d 10.0.0.0/8 -j ACCEPT");
echo shell_exec("iptables -A INPUT -s 172.16.0.0/12 -j ACCEPT");
echo shell_exec("iptables -A OUTPUT -d 172.16.0.0/12 -j ACCEPT");
echo shell_exec("iptables -A INPUT -s 192.168.0.0/16 -j ACCEPT");
echo shell_exec("iptables -A OUTPUT -d 192.168.0.0/16 -j ACCEPT");

// Allow VPN establishment with only 2 ports open, 1 for DNS and 1 for VPN
// If establishing thru an IP and not DNS, the ones with port 53 can be removed
// Port may be different depending on the VPN

echo "\n\n[Updating iptables to allow for 2 ports of communication]\n";
echo shell_exec("iptables -A INPUT -p $Port1Type --sport $Port1Number -j ACCEPT");
echo shell_exec("iptables -A OUTPUT -p $Port1Type --dport $Port1Number -j ACCEPT");
echo shell_exec("iptables -A INPUT -p $Port2Type --sport $Port2Number -j ACCEPT");
echo shell_exec("iptables -A OUTPUT -p $Port2Type --dport $Port2Number -j ACCEPT");

//Accept all TUN connections (tun = VPN tunnel)
echo "\n\n[Updating iptables to allow for all TUN connection traffic]\n";
echo shell_exec("iptables -A OUTPUT -o tun+ -j ACCEPT");
echo shell_exec("iptables -A INPUT -i tun+ -j ACCEPT");

#Set default policies to drop all communication unless specifically allowed
echo "\n\n[Updating iptable to drop all communication unless specifically allowed]\n";
echo shell_exec("iptables -P INPUT DROP");
echo shell_exec("iptables -P OUTPUT DROP");
echo shell_exec("iptables -P FORWARD DROP");

echo "\n\n[Bringin up and down the network interface]\n";
echo shell_exec("ifconfig $NetworkInterfaceName down");
echo shell_exec("ifconfig $NetworkInterfaceName up");

echo "\n\n[Stopping the OpenVPN Service]\n";
echo shell_exec("service openvpn stop");
sleep(5);

echo "\n\n[Starting the OpenVPN Service]\n";
echo shell_exec("service openvpn start");
sleep(5);

# If the script has any problems this is where it is.

echo "\n\n[Installing iptables-persistent']\n";
echo shell_exec("echo iptables-persistent iptables-persistent/autosave_v4 boolean true | sudo debconf-set-selections");
echo shell_exec("echo iptables-persistent iptables-persistent/autosave_v6 boolean true | sudo debconf-set-selections");
echo shell_exec("apt-get install iptables-persistent -y");

echo "\n\n[Turning on netfilter-persistent save]\n";
echo shell_exec("netfilter-persistent save");

echo "\n\n[Enabeling netfilter-persistent to start on restart']\n";
echo shell_exec("systemctl enable netfilter-persistent");

?>
