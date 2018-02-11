#!/usr/local/bin/php
<?php
/*
 * pfmonitor.checkinopn.php - For OPNSense 18.1.2 and higher
 *
 * part of pfMonitor (https://www.black-knights.org)
 * Copyright (c) 2017 MasterX-BKC-
 * All rights reserved.
 */
// Safe up our execution environment
ini_set('max_execution_time', 15);

$serialkey = "serialkey.txt";
if (file_exists($serialkey)) {
	$serial = file_get_contents($serialkey);
} else {
	$serial = rand(10000000000000, 99999999999999);
	file_put_contents($serialkey, $serial);
}

require_once('config.inc');
require_once('system.inc');

$cronfile = '/var/cron/tabs/root';
$crontask = @file_get_contents($cronfile);
if(strripos($crontask, 'PFMonitor/checkin.php') === false) {
	echo "PFMonitor Cron job not found!  Installing...\n";
        system_cron_configure();
}

// Config file location
$confdata = "/conf/config.xml";

// Get root filesystem storage status
$hddcmd = exec("df -h | grep rootfs", $filesystems);
$filesystems = explode("    ", $filesystems[0]);
$filesystems = $filesystems[4];
$cpucmd = exec("sysctl hw.model", $cpumodel);
$cpumodel = explode(": ", $cpumodel[0]);
$cpumodel = $cpumodel[1];
$cpucmd = exec("sysctl hw.machine", $cpuarch);
$cpuarch = $cpuarch[0];
$cpucmd = exec("sysctl hw.ncpu", $cpucount);
$cpucount = explode(": ", $cpucount[0]);
$cpucount = $cpucount[1];
$cpucmd = exec("uname -m", $fwarch);
$memcmd = exec("grep memory /var/run/dmesg.boot | grep real", $memtotal);
$memcmd = exec("grep memory /var/run/dmesg.boot | grep avail", $memfree);
$memtotal = $memtotal[0];
$memtotal = explode("(", $memtotal);
$memtotal = str_replace(" MB)", "", $memtotal[1]);
$memfree = $memfree[0];
$memfree = explode("(", $memfree);
$memfree = str_replace(" MB)", "", $memfree[1]);
$memUsage = ($memfree / $memtotal) * 100 - 100;
$memUsage = $memUsage * -1;
$memUsage = round($memUsage);
$memUsage .= "% of " . $memtotal . "MiB";
$uptcmd = exec("uptime | awk -F'( |,|:)+' '{print $4,$5\",\",$6,\"hours,\",$7,\"minutes.\"}'", $uptime);
$ldscmd = exec("uptime", $loads);
$loads = explode("load averages: ", $loads[0]);
$uptime = str_replace("up ", "", $uptime[0]);
$loads = $loads[1];
$sttcmd = exec("pfctl -si | grep \"current entries\" | awk '{ print $3 }'", $states);
$states = $states[0];
$sttcmd = exec("pfctl -sm | grep \"states\" | awk '{ print $4 }'", $statelimit);
$statelimit = $statelimit[0];

// Do IPSEC Report
$vpnreport = "";
$ipsec_status = json_decode(configd_run("ipsec list status"), true);
if ($ipsec_status == null) {
    $ipsec_status = array();
}
foreach ($ipsec_status as $ipsec_conn_key => $ipsec_conn) {
	if (count($ipsec_conn['sas'])) {
		foreach ($ipsec_conn['sas'] as $sa_key => $sa) {
			foreach ($sa['child-sas'] as $child_sa_key => $child_sa) {
				if($child_sa['state'] == "INSTALLED") {
					$vpnreport .= "Tunnel to " . $ipsec_conn['remote-addrs'] . " (" . $child_sa['remote-ts'][0] . ") UP!\n";
				} else {
					$vpnreport .= "Tunnel to " . $ipsec_conn['remote-addrs'] . " (" . $child_sa['remote-ts'][0] . ") DOWN!\n";
				}
			}
		}
	} else {
		$vpnreport .= "Tunnel to " . $ipsec_conn['remote-addrs'] . " DOWN!\n";
	}
}
if($vpnreport == "") {
	$vpnreport = "No Tunnels Exist.";
}

// Prepare final data needed for checkin to pfmonitor
//$pfstatetext = get_pfstate();
//$pfstateusage = get_pfstate(true);

$hostname = htmlspecialchars($config['system']['hostname'] . "." . $config['system']['domain']);
//$serial = system_get_serial();
$version = strtok(file_get_contents('/usr/local/opnsense/version/opnsense'), '-');
$arch = php_uname("m");
$cpu = htmlspecialchars($cpumodel);
$cores = $cpucount;
$dateconfig = htmlspecialchars(date("D M j G:i:s T Y", intval($config['revision']['time'])));
$stateUsage = ($states / $statelimit) * 100;
$stateUsage = round($stateUsage);
$states = $stateUsage . "% " . $states . "/" . $statelimit;
$memory = $memUsage;
$cleanver = preg_replace("/[^0-9]/", '', $version);
if($cleanver >= "1714") {
	$_gb = exec('/bin/kenv -q smbios.bios.vendor 2>/dev/null', $biosvendor);
	$_gb = exec('/bin/kenv -q smbios.bios.version 2>/dev/null', $biosversion);
	$_gb = exec('/bin/kenv -q smbios.bios.reldate 2>/dev/null', $biosdate);
	if(!empty($biosvendor[0]) || !empty($biosversion[0]) || !empty($biosdate[0])) {
		$biosvendor = $biosvendor[0];
		$biosversion = $biosversion[0];
		$biosdate = $biosdate[0];
		$data = array('hostname' => $hostname, 'serial' => $serial, 'version' => $version, 'arch' => $arch, 'cpu' => $cpu, 'cores' => $cores, 'uptime' => $uptime, 'config' => $dateconfig, 'states' => $states, 'loads' => $loads, 'memory' => $memory, 'biosvendor' => $biosvendor, 'biosversion' => $biosversion, 'biosdate' => $biosdate, 'vpnreport' => $vpnreport);
	} else {
		$data = array('hostname' => $hostname, 'serial' => $serial, 'version' => $version, 'arch' => $arch, 'cpu' => $cpu, 'cores' => $cores, 'uptime' => $uptime, 'config' => $dateconfig, 'states' => $states, 'loads' => $loads, 'memory' => $memory, 'vpnreport' => $vpnreport);
	}
} else {
	$data = array('hostname' => $hostname, 'serial' => $serial, 'version' => $version, 'arch' => $arch, 'cpu' => $cpu, 'cores' => $cores, 'uptime' => $uptime, 'config' => $dateconfig, 'states' => $states, 'loads' => $loads, 'memory' => $memory, 'vpnreport' => $vpnreport);
}

error_reporting(E_NONE);
$url = 'https://admin.pfmonitor.com/checkin.php';
$cfgurl = 'https://admin.pfmonitor.com/configupload.php';
$scrurl = 'https://admin.pfmonitor.com/screenupload.php';
$tlmurl = 'https://admin.pfmonitor.com/telemetry.php';

// Process Checkin, and get input from pfmonitor.
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 4);
curl_setopt($curl, CURLOPT_TIMEOUT, 8);
$response = curl_exec($curl);
curl_close($curl);
$command = $response;
$data = "";


// Periodic Tasks to ensure freshness
// Backup Config to the Cloud every 6 hours
if(date("H:i") == "00:00" || date("H:i") == "06:00" || date("H:i") == "12:00" || date("H:i") == "18:00") {
	$cfgdata = file_get_contents($confdata);
	$cfgdata = base64_encode($cfgdata);
	$cfgupload = curl_init($cfgurl);
	$data = array('serial' => $serial, 'config' => $dateconfig, 'data' => $cfgdata);
	curl_setopt($cfgupload, CURLOPT_POST, true);
	curl_setopt($cfgupload, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($cfgupload, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($cfgupload, CURLOPT_CONNECTTIMEOUT, 4);
	curl_setopt($cfgupload, CURLOPT_TIMEOUT, 8);
	$response = curl_exec($cfgupload);
	curl_close($cfgupload);
}
// Capture Screen
if(date("i") == "00" || date("i") == "10" || date("i") == "20" || date("i") == "30" || date("i") == "40" || date("i") == "50") {
	exec('echo -n "" | /usr/local/etc/rc.initial.banner 2>/dev/null', $banner);
	exec('echo -n "" | /usr/local/etc/rc.initial 2>/dev/null', $console);
	$banner = implode("\n", $banner);
	$console = implode("\n", $console);
	$console = $banner . $console;
	$scrdata = base64_encode($console);
	$scrupload = curl_init($scrurl);
	$data = array('serial' => $serial, 'config' => $dateconfig, 'data' => $scrdata);
	curl_setopt($scrupload, CURLOPT_POST, true);
	curl_setopt($scrupload, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($scrupload, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($scrupload, CURLOPT_CONNECTTIMEOUT, 4);
	curl_setopt($scrupload, CURLOPT_TIMEOUT, 8);
	$response = curl_exec($scrupload);
	curl_close($scrupload);
}
// Recycle PHP-FPM
//if(date("i") == "00" || date("i") == "30") {
//	exec('/etc/rc.php-fpm_restart');
//}

//if(date("i") == "00" || date("i") == "05" || date("i") == "10" || date("i") == "15" || date("i") == "20" || date("i") == "25" || date("i") == "30" || date("i") == "35" || date("i") == "40" || date("i") == "45" || date("i") == "50" || date("i") == "55") {
//	$ifdescrs = get_configured_interface_with_descr(false, true);
//	$senddata = "";
//	foreach ($ifdescrs as $ifdescr => $ifname) {
//		$ifinfo = get_interface_info($ifdescr);
//		//$mac_man = load_mac_manufacturer_table();
//		$senddata .= "Interface Name: " . strtoupper($ifdescr) . "\n";
//		$senddata .= "Hardware Interface: " . $ifinfo['hwif'] . "\n";
//		$senddata .= "Software Interface: " . $ifinfo['if'] . "\n";
//		$senddata .= "Status: " . $ifinfo['status'] . "\n";
//		$senddata .= "Media: " . $ifinfo['media'] . "\n";
//		$senddata .= "MAC Address: " . $ifinfo['macaddr'] . "\n";
//		$senddata .= "MTU: " . $ifinfo['mtu'] . "\n";
//		$senddata .= "Primary IP: " . $ifinfo['ipaddr'] . "\n";
//		$senddata .= "Subnet Mask: " . $ifinfo['subnet'] . "\n";
//		$senddata .= "Gateway: " . $ifinfo['gateway'] . "\n";
//		$senddata .= "Link Local: " . $ifinfo['linklocal'] . "\n";
//		$senddata .= "Primary IPv6: " . $ifinfo['ipaddrv6'] . "\n";
//		$senddata .= "Subnet Mask v6: " . $ifinfo['subnetv6'] . "\n";
//		$senddata .= "Gateway v6: " . $ifinfo['gatewayv6'] . "\n";
//		$senddata .= "IN/OUT Errors: " . $ifinfo['inerrs'] . "/" . $ifinfo['outerrs'] . "\n";
//		$senddata .= "Collisions: " . $ifinfo['collisions'] . "\n";
//		$senddata .= "IN/OUT Packets: " . $ifinfo['inpkts'] . "/" . $ifinfo['outpkts'] . " (" . round(($ifinfo['inbytes'] / 1000 / 1000 / 1000)) . " GiB/" . round($ifinfo['outbytes'] / 1000 / 1000 / 1000) . " GiB)\n\n";
//	}
//	$senddata = base64_encode($senddata);
//	$tlmupload = curl_init($tlmurl);
//	$type = "ints";
//	$data = array('serial' => $serial, 'type' => $type, 'data' => $senddata);
//	curl_setopt($tlmupload, CURLOPT_POST, true);
//	curl_setopt($tlmupload, CURLOPT_POSTFIELDS, http_build_query($data));
//	curl_setopt($tlmupload, CURLOPT_RETURNTRANSFER, true);
//	curl_setopt($tlmupload, CURLOPT_CONNECTTIMEOUT, 4);
//	curl_setopt($tlmupload, CURLOPT_TIMEOUT, 8);
//	$response = curl_exec($tlmupload);
//	curl_close($tlmupload);
//}


if($command == "success") {
	echo $command;
	die;
} elseif($command == "reboot") {
	echo $command;
	exec('echo "y" | /usr/local/etc/rc.initial.reboot');
} elseif($command == "upgrade") {
	exec('echo "y" | /usr/local/etc/rc.initial.firmware');
	die;
} elseif($command == "update") {
	exec("/bin/rm /usr/local/www/pfmonitor.checkinopn.php ; cd /usr/local/www/ ; fetch https://admin.pfmonitor.com/pfmonitor.checkinopn.php");
	exec('service php-fpm onerestart');
	exec('/usr/local/etc/rc.restart_webgui');
	die;
} elseif($command == "flush") {
	exec('service php-fpm onerestart');
	exec('/usr/local/etc/rc.restart_webgui');
	die;
} elseif($command == "dolists") {
	// Manually expire the alias tables
	exec("/usr/bin/touch -t 1001031305 /var/db/aliastables/*");
	// Trigger their re-download
	exec("/usr/bin/nice -n20 /usr/local/etc/rc.update_urltables");
	die;
} elseif($command == "delock") {
	// Empty the lockouts
	exec("pfctl -T flush -t webConfiguratorlockout");
	die;
} elseif($command == "passwd") {
	// Reset password to default
	exec('echo "y" | /usr/local/etc/rc.initial.password');
	die;
} elseif($command == "fltreload") {
	// Reload Filters
	exec('/usr/local/etc/rc.filter_configure');
	die;
} elseif($command == "ovpnreset") {
	// Restart OpenVPN Processes
	require_once('openvpn.inc');
	openvpn_resync_all();
} elseif($command == "backup") {
	$cfgdata = file_get_contents($confdata);
	$cfgdata = base64_encode($cfgdata);
	$cfgupload = curl_init($cfgurl);
	$data = array('serial' => $serial, 'config' => $dateconfig, 'data' => $cfgdata);
	curl_setopt($cfgupload, CURLOPT_POST, true);
	curl_setopt($cfgupload, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($cfgupload, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($cfgupload, CURLOPT_CONNECTTIMEOUT, 4);
	curl_setopt($cfgupload, CURLOPT_TIMEOUT, 8);
	$response = curl_exec($cfgupload);
	curl_close($cfgupload);
} elseif($command == "console") {
	exec('echo -n "" | /usr/local/etc/rc.initial.banner 2>/dev/null', $banner);
	exec('echo -n "" | /usr/local/etc/rc.initial 2>/dev/null', $console);
	$banner = implode("\n", $banner);
	$console = implode("\n", $console);
	$console = $banner . $console;
	$scrdata = base64_encode($console);
	$scrupload = curl_init($scrurl);
	$data = array('serial' => $serial, 'config' => $dateconfig, 'data' => $scrdata);
	curl_setopt($scrupload, CURLOPT_POST, true);
	curl_setopt($scrupload, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($scrupload, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($scrupload, CURLOPT_CONNECTTIMEOUT, 4);
	curl_setopt($scrupload, CURLOPT_TIMEOUT, 8);
	$response = curl_exec($scrupload);
	curl_close($scrupload);
}

die();
