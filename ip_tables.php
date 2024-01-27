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
        exit("Ending script.  Not running as root.\n");
    };

// Run updates
echo "\n\n[Running 'apt-get update -y']\n";
echo shell_exec('apt-get update -y');

echo "\n\n[Running 'apt-get upgrade -y']\n";
echo shell_exec('apt-get upgrade -y');

// Install needed software
echo "\n\n[Running 'apt-get install php-zip -y']\n";
echo shell_exec('apt-get install php-zip -y');

echo "\n\n[Running 'apt-get install ipupdown -y']\n";
echo shell_exec('apt-get install ifupdown -y');

echo "\n\n[Running 'apt-get install openvpn -y']\n";
echo shell_exec('apt-get install openvpn -y');

echo "\n\n[Running 'apt-get install -f resolvconf -y']\n";
echo shell_exec('apt-get install -f resolvconf -y');

// Get VPN files
$output = file_get_contents($url);
file_put_contents($LocalZipFile, $output);

// Start and Enable resolvconf service to start on boot

echo "\n\n[Running 'systemctl start resolvconf.service']\n";
echo shell_exec("systemctl start resolvconf.service");

echo "\n\n[Running 'systemctl enable resolvconf.service']\n";
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
        fwrite($output, $piaPassword . PHP_EOL);
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



?>
