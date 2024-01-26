<?php

// This script obviously requires the installation of php
// This script installs PIA VPN using OpenVPN and sets up a killswitch using iptables
// This script needs to be run as root.

// PIA Credentials
$PiaUsername = "username";
$piaPassword = "password";

// Address of the Network (not the machine IP but of the network) with the / number as well
$networkAddress = "192.168.1.0/24";

// Network interface name
$networkInterfaceName = "enp0s3";

// UDP Ports that are left open
// Default ports are 53 for DNS and 1197 for VPN which are both UDP
// Ports may change or differ

$port1Number="53";
$port1Type="UDP";

$port2Number="1197";
$port2Type="UDP";

// Some variables
$LocationName="ca_toronto.ovpn";
$OpenVpnLocation = "/etc/openvpn/";
$VpnConfigFilename = "vpn.conf";
$CredentialFilename = "login";
$OldPattern = "auth-user-pass";
$NewPattern = "auth-user-pass /etc/openvpn/$CredentialFilename";
$LocalZipFile = "openvpn-strong.zip";
$url = "https://www.privateinternetaccess.com/openvpn/openvpn-strong.zip";

// Verify if running as root
if (posix_getuid() !== 0){
        exit ("This is non-root");
    };

// Run updates
#echo shell_exec('apt update -y');
#echo shell_exec('apt upgrade -y');

// Install needed software
#echo shell_exec('apt-get install -f ifupdown openvpn resolvconf php-zip -y');

// Restart resolvconf service
#echo shell_exec("systemctl start resolvconf.service");

// Get VPN files
$output = file_get_contents($url);
file_put_contents($LocalZipFile, $output);

$output = new ZipArchive;
if ($output->open($LocalZipFile) === TRUE) {
    $output->extractTo($OpenVpnLocation);
    $output->close();
} else {
    exit ("Exiting script. Unzip failed\n");
}

// Delete the zip file
unlink($LocalZipFile);

// Change directory
chdir($OpenVpnLocation);

// Create credential file

$output = fopen($CredentialFilename, "w");
if ($output) 
        fwrite($output, $PiaUsername . PHP_EOL);
        fwrite($output, $piaPassword . PHP_EOL);
        fclose($output);
} else {
    exit ("Exiting script. Unable to create $CredentialFilename\n");
}

// Update permissions on the credential file
chmod($CredentialFilename, 0500);

// Copy the openvpn file into the appropriate filename
copy($LocationName, $VpnConfigFilename);

// Update to have auth with login in the VpnConfigFilename
$fileContent = file_get_contents($VpnConfigFilename);
$newFileContent = str_replace($OldPattern, $NewPattern, $fileContent);
file_put_contents($VpnConfigFilename, $newFileContent);

?>
