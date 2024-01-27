<?php

########## This script requires preinstallation of php 5.2.0 or higher and pip-zip prior to running
########## This script installs PIA VPN using OpenVPN and sets up a killswitch using iptables
########## This script needs to be run as root.

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

// Determine if package is installed

        function isPackageInstalled($packageName)
        {
            $output = shell_exec("dpkg-query -W --showformat='\${Status}\n' $packageName");
            return strpos($output, "install ok installed") !== false;
        }

// Installs software using the isPackageInstalled function

        function installSoftware($packageName)
        {
          if (!isPackageInstalled($packageName)) {
              echo "\n\n[Installing $packageName]\n";
              echo shell_exec("apt-get install $packageName -y");
          }
        }

// Start, Stop, or Enable service

        function workService($serviceName, $enterState)
        {
            echo "\n[Running systemctl $enterState $serviceName]";
            echo shell_exec("systemctl $enterState $serviceName");
        }

// Verify if running as root

        if (posix_getuid() !== 0){
            exit("Ending script.  Not running as root.\n");
        };

// Run updates

        echo "\n\n[Running Update]\n";
        echo shell_exec('apt-get update -y');

        echo "\n\n[Running Upgrade]\n";
        echo shell_exec('apt-get upgrade -y');

// Verify if zip is installed

        if (!isPackageInstalled('php-zip')) {
            installSoftware('php-zip');
            exit("\nJust installed php-zip. Restart the script\n");
        }

// Install needed software

        installSoftware('ifupdown');
        installSoftware('openvpn');
        installSoftware('resolvconf');

        echo "\n\n[PreDownloading iptables-persistent]\n";
        echo shell_exec('apt-get install --download-only iptables-persistent -y');

// Get VPN zip file

        $output = file_get_contents($url);
        file_put_contents($LocalZipFile, $output);

// Start and Enable resolvconf service to start on boot

        workService('resolvconf.service','start');
        workService('resolvconf.service','enable');

// Extract zip file into place

        $output = new ZipArchive;
        if ($output->open($LocalZipFile) === TRUE) {
            echo "\n\n[Unzipping file $LocalZipFile into $OpenVpnLocation]\n";
            $output->extractTo($OpenVpnLocation);
            $output->close();
        } else {
            exit ("Exiting script. Unzip failed\n");
        }

// Delete the original zip file

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

// Helps to ensure no DNS leaks

        echo "\n\n[Updating $VpnConfigFilename to help ensure no DNS leaks]\n";
        $myfile = file_put_contents($VpnConfigFilename, $DataAppend1.PHP_EOL , FILE_APPEND | LOCK_EX);

// Enable openvpn with the config file to start automatically

        $FileNameParts = pathinfo($VpnConfigFilename);
        $VpnConfigFileBasename = $FileNameParts['filename']; // filename is only since PHP 5.2.0        //Baasename is the filename without the extension
        workService("openvpn@$VpnConfigFileBasename","enable");
        //echo "\n\n[Running 'systemctl enable openvpn@$VpnConfigFileBasename']\n";
        //echo shell_exec("systemctl enable openvpn@$VpnConfigFileBasename");

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

// Allow all local traffic.

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

// Accept all TUN connections (tun = VPN tunnel)

        echo "\n\n[Updating iptables to allow for all TUN connection traffic]\n";
        echo shell_exec("iptables -A OUTPUT -o tun+ -j ACCEPT");
        echo shell_exec("iptables -A INPUT -i tun+ -j ACCEPT");

// Set default policies to drop all communication unless specifically allowed

        echo "\n\n[Updating iptable to drop all communication unless specifically allowed]\n";
        echo shell_exec("iptables -P INPUT DROP");
        echo shell_exec("iptables -P OUTPUT DROP");
        echo shell_exec("iptables -P FORWARD DROP");

// Bring up and down the network inteface with pauses

        echo "\n\n[Bringin up and down the network interface]\n";
        echo shell_exec("ip link set $NetworkInterfaceName down");
        echo shell_exec("ip link set $NetworkInterfaceName up");

// Stopping and starting openvpn service with pauses

        workService('openvpn','stop');
        sleep(5);

        workService('openvpn','start');
        sleep(5);

// Installing iptables-persistent to save iptable rules on reboot

        echo shell_exec("echo iptables-persistent iptables-persistent/autosave_v4 boolean true | sudo debconf-set-selections");
        echo shell_exec("echo iptables-persistent iptables-persistent/autosave_v6 boolean true | sudo debconf-set-selections");
        installSoftware('iptables-persistent');

// Turning on netfilter-persistent and setting to start on restart

        echo shell_exec("netfilter-persistent save");
        workService('netfilter-persistent','enable');

?>
