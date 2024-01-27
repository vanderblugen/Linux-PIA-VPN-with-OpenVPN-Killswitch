<?php

########## This script requires preinstallation of php 5.2.0 or higher and pip-zip prior to running
########## This script installs PIA VPN using OpenVPN and sets up a killswitch using iptables
########## This script needs to be run as root.

###################################################################################################################################
############################################################ VARIABLES ############################################################
###################################################################################################################################

$PiaUsername = "username";                                                      // PIA Username
$PiaPassword = "password";                                                      // PIA Password

// Name of the network interface
$NetworkInterfaceName = "enp0s3";                                               // Network interface name

// UDP Ports that are left open.  Default ports are 53 for DNS and 1197 for VPN which are both UDP.  Ports may differ
$Port1Number="53";
$Port1Type="UDP";
$Port2Number="1197";
$Port2Type="UDP";

$OpenVpnLocation = "/etc/openvpn/";                                             // Location where the openvpn files are going to be located
$LocalZipFile = "openvpn-strong.zip";                                           // Local zip file name
$url = "https://www.privateinternetaccess.com/openvpn/openvpn-strong.zip";      // Online zip file URL
$LocationName="ca_toronto.ovpn";                                                // Openvpn filename for the country

$VpnConfigFilename = "vpn.conf";                                                //VPN Config filename
$CredentialFilename = "login";                                                  // Login credentials stored in this file

$OldPattern = "auth-user-pass";
$NewPattern = "auth-user-pass ${OpenVpnLocation}${CredentialFilename}";

//Helps to ensure no DNS leaks
$DataAppend1 = "script-security 2" . "\nup ${OpenVpnLocation}update-resolv-conf" . "\ndown ${OpenVpnLocation}update-resolv-conf";

$LogFileName = dirname(__FILE__) . "/" . basename(__FILE__, ".php") . ".log";   // Most outputs are logged this file

###################################################################################################################################
###################################################################################################################################
###################################################################################################################################

// DateTime outputs

        function givemeDateTimeNow() {
            $output = "\n************************************\n";
            $output .= date('m/d/Y h:i:s a', time());
            $output .= "\n************************************\n";
            return $output;
        }

// Determine if package is installed

        function isPackageInstalled($packageName) {
            $output = shell_exec("dpkg-query -W --showformat='\${Status}\n' $packageName");
            return strpos($output, "install ok installed") !== false;
        }

// Runs the command and outputs to the log function

        function runThis($thisCommand) {
            GLOBAL $LogFileName;
            $output = shell_exec($thisCommand);
            file_put_contents($LogFileName,$output,FILE_APPEND);
        }

// Log function to echo and put text to a file

        function logThis($output,$EchoMe) {
            GLOBAL $LogFileName;
            if ($EchoMe) echo "$output\n";
            file_put_contents($LogFileName,"\n$output\n\n",FILE_APPEND);
        }

// Installs software using the isPackageInstalled function

        function installSoftware($packageName) {
            GLOBAL $LogFileName;
            if (!isPackageInstalled($packageName)) {
              logThis("[Installing $packageName]", true);
              $output = shell_exec("apt-get install $packageName -y");
              file_put_contents($LogFileName,$output,FILE_APPEND);
          }
        }

// Start, Stop, or Enable service

        function workService($serviceName, $enterState) {
            GLOBAL $LogFileName;
            logThis("[Running systemctl $enterState $serviceName]",true);
            $output = shell_exec("systemctl $enterState $serviceName");
            file_put_contents($LogFileName,$output,FILE_APPEND);
        }

// Verify if running as root

        if (posix_getuid() !== 0) {
            exit("Ending script.  Not running as root.\n");
        };

// Run updates

        logThis(givemeDateTimeNow(),false);

        logThis("[Running Update]",true);
        runThis('apt-get update -y');

        logThis("[Running Upgrade]",true);
        runThis('apt-get upgrade -y');

// Verify if zip is installed

        if (!isPackageInstalled('php-zip')) {
            installSoftware('php-zip');
            exit("\ust installed php-zip. Restart the script\n");
        }

// Install needed software

        installSoftware('ifupdown');
        installSoftware('openvpn');
        installSoftware('resolvconf');

        logThis("[PreDownloading iptables-persistent]",true);
        runThis('apt-get install --download-only iptables-persistent -y');

// Get VPN zip file

        $output = file_get_contents($url);
        file_put_contents($LocalZipFile, $output);

// Start and Enable resolvconf service to start on boot

        workService('resolvconf.service','start');
        workService('resolvconf.service','enable');

// Extract zip file into place

        $output = new ZipArchive;
        if ($output->open($LocalZipFile) === TRUE) {
            logThis("[Unzipping file $LocalZipFile into $OpenVpnLocation]",true);
            $output->extractTo($OpenVpnLocation);
            $output->close();
        } else {
            exit ("Exiting script. Unzip failed\n");
        }

// Delete the original zip file

        logThis("[Deleting Zip File $LocalZipFile]",true);
        unlink($LocalZipFile);

// Change directory

        logThis("[Changing directory to $OpenVpnLocation]",true);
        chdir($OpenVpnLocation);

// Create credential file

        $output = fopen($CredentialFilename, "w");
        if ($output) {
                logThis("[Creating Credential File $CredentialFilename]",true);
                fwrite($output, $PiaUsername . PHP_EOL);
                fwrite($output, $PiaPassword . PHP_EOL);
                fclose($output);
        } else {
            exit ("Exiting script. Unable to create $CredentialFilename\n");
        }

// Update permissions on the credential file

        logThis("[Changing permissions on $CredentialFilename]",true);
        chmod($CredentialFilename, 0500);

// Copy the openvpn file into the appropriate filename

        logThis("[Copying $LocationName to $VpnConfigFilename]",true);
        copy($LocationName, $VpnConfigFilename);

// Update to have auth with login in the VpnConfigFilename

        logThis("Updating $VpnConfigFilename with login file]",true);
        $fileContent = file_get_contents($VpnConfigFilename);
        $newFileContent = str_replace($OldPattern, $NewPattern, $fileContent);
        file_put_contents($VpnConfigFilename, $newFileContent);

// Helps to ensure no DNS leaks

        logThis("[Updating $VpnConfigFilename to help ensure no DNS leaks]",true);
        $myfile = file_put_contents($VpnConfigFilename, $DataAppend1.PHP_EOL,FILE_APPEND | LOCK_EX);

// Enable openvpn with the config file to start automatically

        $FileNameParts = pathinfo($VpnConfigFilename);
        $VpnConfigFileBasename = $FileNameParts['filename']; // filename is only since PHP 5.2.0        //Baasename is the filename without the extension
        workService("openvpn@$VpnConfigFileBasename","enable");

// Clearing out old IP tables rules

        logThis("[Clear out the old IP tables rules]",true);
        runThis("iptables -F");
        runThis("iptables -t nat -F");
        runThis("iptables -t mangle -F");
        runThis("iptables -X");

// Allow loopback device (internal communication)

        logThis("[Updating iptables to allow for loopback device (internal communication)]",true);
        runThis("iptables -A INPUT -i lo -j ACCEPT");
        runThis("iptables -A OUTPUT -o lo -j ACCEPT");

// Allow all local traffic.

        logThis("[Updating iptables to allow for all local traffic]",true);
        runThis("iptables -A INPUT -s 10.0.0.0/8 -j ACCEPT");
        runThis("iptables -A OUTPUT -d 10.0.0.0/8 -j ACCEPT");
        runThis("iptables -A INPUT -s 172.16.0.0/12 -j ACCEPT");
        runThis("iptables -A OUTPUT -d 172.16.0.0/12 -j ACCEPT");
        runThis("iptables -A INPUT -s 192.168.0.0/16 -j ACCEPT");
        runThis("iptables -A OUTPUT -d 192.168.0.0/16 -j ACCEPT");

// Allow VPN establishment with only 2 ports open, 1 for DNS and 1 for VPN
// If establishing thru an IP and not DNS, the ones with port 53 can be removed
// Port may be different depending on the VPN

        logThis("[Updating iptables to allow for 2 ports of communication]",true);
        runThis("iptables -A INPUT -p $Port1Type --sport $Port1Number -j ACCEPT");
        runThis("iptables -A OUTPUT -p $Port1Type --dport $Port1Number -j ACCEPT");
        runThis("iptables -A INPUT -p $Port2Type --sport $Port2Number -j ACCEPT");
        runThis("iptables -A OUTPUT -p $Port2Type --dport $Port2Number -j ACCEPT");

// Accept all TUN connections (tun = VPN tunnel)

        logThis("[Updating iptables to allow for all TUN connection traffic]",true);
        runThis("iptables -A OUTPUT -o tun+ -j ACCEPT");
        runThis("iptables -A INPUT -i tun+ -j ACCEPT");

// Set default policies to drop all communication unless specifically allowed

        logThis("[Updating iptable to drop all communication unless specifically allowed]",true);
        runThis("iptables -P INPUT DROP");
        runThis("iptables -P OUTPUT DROP");
        runThis("iptables -P FORWARD DROP");

// Bring up and down the network inteface with pauses

        logThis("[Bringin up and down the network interface]",true);
        runThis("ip link set $NetworkInterfaceName down");
        runThis("ip link set $NetworkInterfaceName up");

// Stopping and starting openvpn service with pauses

        workService('openvpn','stop');
        sleep(5);

        workService('openvpn','start');
        sleep(5);

// Installing iptables-persistent to save iptable rules on reboot

        runThis("echo iptables-persistent iptables-persistent/autosave_v4 boolean true | sudo debconf-set-selections");
        runThis("echo iptables-persistent iptables-persistent/autosave_v6 boolean true | sudo debconf-set-selections");
        installSoftware('iptables-persistent');

// Turning on netfilter-persistent and setting to start on restart

        runThis("netfilter-persistent save");
        workService('netfilter-persistent','enable');

?>
