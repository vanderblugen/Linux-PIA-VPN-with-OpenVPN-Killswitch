<?php

// This script obviously requires the installation of php
// This script installs PIA VPN using OpenVPN and sets up a killswitch using iptables
// This script needs to be run as root.

// PIA Credentials
$pia_username = "";
$pia_password = "";

// Address of the Network (not the machine IP but of the network) with the / number as well
$network_address = "192.168.1.0/24";

// Network interface name
$network_interface_name = "enp0s3";

// UDP Ports that are left open
// Default ports are 53 for DNS and 1197 for VPN which are both UDP
// Ports may change or differ

$port1_number="53";
$port1_type="UDP";

$port2_number="1197";
$port2_type="UDP";

// Name of country filename
$filename="CA Toronto.ovpn";

// Verify if running as root
if (posix_getuid() !== 0){
        exit ("This is non-root");
    };

// Run updates
echo shell_exec('apt update -y');
echo shell_exec('apt upgrade -y');

// Install needed software
echo shell_exec('apt-get install -f ifupdown openvpn resolvconf php-zip -y');

// Restart resolvconf service
echo shell_exec("systemctl start resolvconf.service");

// Get VPN files
$local_zip_file = "openvpn-strong.zip";
$url = "https://www.privateinternetaccess.com/openvpn/openvpn-strong.zip";
$zip_file = file_get_contents($url);
file_put_contents($local_zip_file, $zip_file);

// Unzip the file into the appropriate directory
$unzip_location = "/etc/openvpn";

$zip = new ZipArchive;
if ($zip->open($local_zip_file) === TRUE) {
    $zip->extractTo($unzip_location);
    $zip->close();
    echo ("Unzipped $local_zip_file into $unzip_location\n");
} else {
    exit ("Exiting script.\nUnzip failed\n");
}

// Delete the zip file
if (!unlink($local_zip_file)) { 
    exit ("Exiting script.\nUnable to delete $local_zip_file");
} 
else { 
    echo ("$local_zip_file has been deleted\n"); 
}

// Change directory
$openvpn_location = "/etc/openvpn";
chdir($openvpn_location);

// Create credential file 
$credential_filename = "login";
$file = fopen($credential_filename, "w");

if ($file) {
    // Write the variables to the file
    fwrite($file, $pia_username . PHP_EOL);
    fwrite($file, $pia_password . PHP_EOL);

    // Close the file
    fclose($file);

    echo ("Created login file successfully\n");
} else {
    exit ("Error creating login file. Exiting.\n");
}

// Update permissions on the credential file
chmod("$openvpn_location/$credential_filename", 0500);

// Copy the openvpn file into the appropriate filename
$vpn_config_filename = "vpn.conf";
copy($filename, $vpn_config_filename);

$old_pattern = "auth-user-pass";
$new_pattern = "auth-user-pass /etc/openvpn/$(credential_filename)";
$output = preg_replace($old_pattern, $new_pattern, $vpn_config_filename);
file_put_contents($vpn_config_filename, $output);


?>
