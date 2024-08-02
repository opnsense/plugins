#!/usr/local/bin/php
<?
// kate: space-indent off; indent-width 4; mixedindent off; indent-mode cstyle;
include "config.inc";

## Exit if we're disabled.
if ( $config['OPNsense']['abuseipdb']["general"]["enabled"] != 1 ) {
	exit;
}

## Exit if we have no filter ID set.
if ( ! $config['OPNsense']['abuseipdb']["general"]["filter_id"] ) {
	exit;
}

## Import config
$flush_on_start =	$config['OPNsense']['abuseipdb']["general"]["flush_on_start"];
$api_key =			$config['OPNsense']['abuseipdb']["general"]["api_key"];
$hits_num =			$config['OPNsense']['abuseipdb']["general"]["packet_count"];
$hits_time =		$config['OPNsense']['abuseipdb']["general"]["packet_timeframe"];
$log_interval =		$config['OPNsense']['abuseipdb']["general"]["log_interval"];
$filter_id =		$config['OPNsense']['abuseipdb']["general"]["filter_id"];

## Write the PID to disk.
file_put_contents("/var/run/abuseipdb.pid", getmypid());
register_shutdown_function('cleanup_on_exit');

## Open up the pf log - /var/log/filter/latest.log
$log = "/var/log/filter/latest.log";
$file = new SplFileObject($log);

## Find the EOF of the log
$file->seek(PHP_INT_MAX);
$eof = $file->key();
$file = null;

## Init known hosts array
$known_ips = array();

## Handle 429 responses from abuseipdb.
$ratelimit_delay = 5;
$ratelimit_delay_max = 180;
$ratelimit_expires = 0;

## Init the last log time.
$log_last = 0;

## Prime the blocklist if we have an API Key.
if ( $api_key != "" ) {
	get_blocklist($api_key, $flush_on_start);
}

while (1) {
	sleep(1);

	## If the log file doesn't exist, just sleep.
	if ( ! file_exists($log) ) { continue; }

	## Process from the last EOF marker...
	$file = new SplFileObject($log);
	$file->seek($eof);

	## Seek to last eof...
	while (!$file->eof()) {
		$elements = explode(',', $file->current());

		## Check if this is our drop rule..
		if ( $elements[3] == $filter_id ) {
			## Process IPv4 line.
			if ( $elements[8] == 4 && ( $elements[16] == 'tcp' || $elements[16] == 'udp' ) ) {
				$src = $elements[18];
				$src_port = $elements[20];
				$dest = $elements[19];
				$dest_port = $elements[21];
				$prot = $elements[16];
			}
			
			## Process IPv6 line.
			if ( $elements[8] == 6 ) {
				$src = $elements[15];
				$src_port = $elements[17];
				$dest = $elements[16];
				$dest_port = $elements[18];
			}

			if ( ! $known_ips[$src] ) {
				$known_ips[$src] = array();
			}

			if ( filter_var($src, FILTER_VALIDATE_IP) ) {
				array_push($known_ips[$src], time());
			}
		}
		$file->next();
	}
	$eof = $file->key();
	$file = null;

	$compare_time = time();
	$known_ips_new = array();
	## Expire any old timestamps
	foreach ( $known_ips as $ip => $timestamps ) {
		$index = 0;
		foreach ( $timestamps as $timestamp ) {
			if ( $timestamp + $hits_time > $compare_time ) {
				if ( ! $known_ips_new[$ip] ) {
					$known_ips_new[$ip] = array();
				}
				array_push($known_ips_new[$ip], $timestamp);
			}
		}
	}
	$known_ips = $known_ips_new;
	unset($known_ips_new);

	$reports_outstanding = 0;
	foreach ( $known_ips as $ip => $timestamps ) {
		## Process anything with more than $hits_num entries.
		if ( count($timestamps) > $hits_num ) {
			## Add to the firewall alias.
			shell_exec("pfctl -q -t abuseipdb -T add $ip");

			if ( $api_key != "" ) {
				if ( time() > $ratelimit_expires ) {
					## Send the report to adbuseipdb.com
					$duration = $known_ips[$ip][count($known_ips[$ip]) -1] - $known_ips[$ip][0] + 1;
					$data = [
						'ip' => $ip,
						'timestamp' => date('c', $known_ips[$ip][0]),
						'categories' => "14",
						'comment' => "Honeypot hits: " . count($timestamps) . " hits in $duration second(s)" 
					];
					$headers = ["Key: $api_key", "Accept: application/json"];
					$url = "https://api.abuseipdb.com/api/v2/report";
					list($result, $ret_code) = http_req("POST", $url, $headers, $data);
					if ( $ret_code == 200 ) {
						unset($known_ips[$ip]);
						echo "Reported $ip successfully\n";
						$ratelimit_expires = 0;
						$ratelimit_delay= 5;
					} else {
						echo "abuseipdb: Got status code: $ret_code - Ratelimiting active...\n";
						$ratelimit_delay *= 2;
						if ( $ratelimit_delay >= $ratelimit_delay_max ) {
							$ratelimit_delay = $ratelimit_delay_max;
						}
						$ratelimit_expires = time() + $ratelimit_delay;
						$reports_outstanding++;
					}
				} else {
					$reports_outstanding++;
				}
			}
		}
	}

	if ( time() > $log_last + $log_interval ) {
		$log_last = time();
		if ( time() < $ratelimit_expires && $reports_outstanding != 0 ) {
			echo "abuseipdb: Ratelimit active. $reports_outstanding reports outstanding\n";
		}
		echo "Tracking " . count($known_ips) . " hosts\n";
	}
}

function get_blocklist($api_key, $flush_on_start) {
	echo "Downloading initial blocklist...\n";
	$data = [ 'confidenceMinimum' => 100, 'limit' => 9999999 ];
	$headers = ["Key: $api_key", "Accept: application/json"];
	$url = "https://api.abuseipdb.com/api/v2/blacklist";
	list($result, $resp_code) = http_req("GET", $url, $headers, $data);

	## Process the list if we got one back.
	if ( $resp_code == 200 ) {
		## Clear the current table...
		if ( $flush_on_start == 1 ) {
			echo "Clearing current table for initial priming...\n";
			shell_exec("pfctl -t abuseipdb -T flush");
		}
		$addresses = array();
		$blocklist = json_decode($result, true);
		foreach ($blocklist["data"] as $entry) {
			if ( $entry["ipAddress"] ) {
				## Ensure we have a valid IP and no surprises
				if ( filter_var($entry["ipAddress"], FILTER_VALIDATE_IP) ) {
					array_push($addresses, $entry["ipAddress"]);
				}
			}

			if ( count($addresses) >= 500 ) {
				shell_exec("pfctl -q -t abuseipdb -T add " . implode(" ", $addresses));
				$addresses = array();
			}
		}

		## Flush any left over entries to pfctl...
		if ( count($addresses) != 0 ) {
			shell_exec("pfctl -q -t abuseipdb -T add " . implode(" ", $addresses));
		}
		echo "Imported " . count($blocklist["data"]) . " entries on startup...\n";
	} else {
		echo "abuseipdb: Got reply code: $resp_code. Not importing anything...\n";
	}
}

function http_req($method, $url, &$headers, &$data) {
	if ( $method == "GET" ) {
		$url = sprintf("%s?%s", $url, http_build_query($data));
	}
	$ch = curl_init($url);
	if ( $method == "POST" ) {
		curl_setopt($ch,CURLOPT_POST, true);
		curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($data));
	}
	curl_setopt($ch,CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 
	$result = curl_exec($ch);

	return array($result, curl_getinfo($ch, CURLINFO_HTTP_CODE));
}

function cleanup_on_exit() {
	unlink "/var/run/abuseipdb.pid";
	exit;
}

?>
