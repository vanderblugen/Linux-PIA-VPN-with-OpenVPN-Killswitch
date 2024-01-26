<?php

// This script requires preinstallation of php 5.2.0 or higher prior to running
// This script installs PIA VPN using OpenVPN and sets up a killswitch using iptables
// This script needs to be run as root.

###################################################################################################################################
############################################################ VARIABLES ############################################################
###################################################################################################################################

$PiaUsername = "username";                                                           // PIA Username
$piaPassword = "password";                                                           // PIA Password

// Address of the Network (not the machine IP but of the network) with the / number as well
$networkAddress = "192.168.1.0/24";
$networkInterfaceName = "enp0s3";                                                    // Network interface name

// UDP Ports that are left open.  Default ports are 53 for DNS and 1197 for VPN which are both UDP.  Ports may differ
$port1Number="53";
$port1Type="UDP";
$port2Number="1197";
$port2Type="UDP";

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
        exit ("This is non-root");
    };

// Run updates
#echo shell_exec('apt update -y');
#echo shell_exec('apt upgrade -y');

// Install needed software
#echo shell_exec('apt-get install -f ifupdown openvpn resolvconf php-zip -y');

// Get VPN files
$output = file_get_contents($url);
file_put_contents($LocalZipFile, $output);

// Restart resolvconf service
#echo shell_exec("systemctl start resolvconf.service");

// Extract ZipFile
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
if ($output) {
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

//Helps to ensure no DNS leaks
$myfile = file_put_contents($VpnConfigFilename, $DataAppend1.PHP_EOL , FILE_APPEND | LOCK_EX);


// enable openvpn with the config file to start automatically
$FileNameParts = pathinfo('/www/htdocs/index.html');
$VpnConfigFileBasename = $FileNameParts['filename'], "\n"; // filename is only since PHP 5.2.0        //Baasename is the filename without the extension
#echo shell_exec("systemctl enable openvpn@${VpnConfigFileBaseName}");



?>
