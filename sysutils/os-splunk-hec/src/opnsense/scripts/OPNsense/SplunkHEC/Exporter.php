#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 pvols79
 * All rights reserved.
 *
 * SPDX-License-Identifier: BSD-2-Clause
 */

/**
 * OPNsense Splunk HEC Plugin – Exporter Daemon
 *
 * Reads configured log files, detects new lines (surviving log rotation via
 * inode tracking), and forwards each line as a JSON event to a Splunk HTTP
 * Event Collector endpoint.
 */

declare(strict_types=1);

define('CONF_PATH',  '/var/etc/splunk_hec.conf');
define('STATE_PATH', '/var/run/splunk_hec_state.json');
define('CACHE_PATH', '/var/run/splunk_hec_cache.log');
define('LOG_PATH',   '/var/log/splunk_hec.log');

function hec_log(string $message): void
{
    $ts = date('Y-m-d\TH:i:sP');
    @file_put_contents(LOG_PATH, "[{$ts}] {$message}\n", FILE_APPEND | LOCK_EX);
}

function load_state(): array
{
    if (!is_readable(STATE_PATH)) return [];
    $json = @file_get_contents(STATE_PATH);
    $data = $json !== false ? json_decode($json, true) : null;
    return is_array($data) ? $data : [];
}

function save_state(array $state): void
{
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    @file_put_contents(STATE_PATH, $json, LOCK_EX);
}

function hec_post(string $endpoint, string $token, string $payload, bool $verifySsl, bool $useGzip): int
{
    $headers = [
        'Authorization: Splunk ' . $token,
        'Content-Type: application/json',
    ];

    if ($useGzip) {
        $payload = gzencode($payload, 6);
        $headers[] = 'Content-Encoding: gzip';
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($code === 0) {
        $err = curl_error($ch);
        echo "ERROR cURL failure: {$err}\n";
        hec_log("ERROR cURL failure: {$err}");
    } elseif ($code !== 200) {
        echo "ERROR Splunk API returned HTTP {$code}. Response: {$response}\n";
        hec_log("ERROR Splunk API returned HTTP {$code}. Response: {$response}");
    }
    
    curl_close($ch);
    return $code;
}

function cache_payload(string $payload): void
{
    @file_put_contents(CACHE_PATH, $payload . "\n", FILE_APPEND | LOCK_EX);
}

function flush_cache(string $endpoint, string $token, int $maxSizeMB, int $maxAgeHours, bool $verifySsl, bool $useGzip): int
{
    if (!is_file(CACHE_PATH) || filesize(CACHE_PATH) === 0) return 0;

    $mtime = filemtime(CACHE_PATH);
    if ($mtime !== false && (time() - $mtime) > ($maxAgeHours * 3600)) {
        hec_log('INFO  Cache expired (>' . $maxAgeHours . ' h) — purging.');
        @unlink(CACHE_PATH);
        return 0;
    }

    if (filesize(CACHE_PATH) > $maxSizeMB * 1024 * 1024) {
        hec_log('WARN  Cache exceeds ' . $maxSizeMB . ' MB — purging.');
        @unlink(CACHE_PATH);
        return 0;
    }

    $lines  = file(CACHE_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $failed = [];
    $ok     = 0;
    
    $batch = '';
    $batchCount = 0;

    foreach ($lines as $line) {
        $batch .= $line . "\n";
        $batchCount++;
        
        if ($batchCount >= 500) {
            $code = hec_post($endpoint, $token, $batch, $verifySsl, $useGzip);
            if ($code === 200) {
                $ok += $batchCount;
            } else {
                $failed[] = trim($batch);
            }
            $batch = '';
            $batchCount = 0;
        }
    }
    
    if ($batchCount > 0) {
        $code = hec_post($endpoint, $token, $batch, $verifySsl, $useGzip);
        if ($code === 200) {
            $ok += $batchCount;
        } else {
            $failed[] = trim($batch);
        }
    }

    if (count($failed) > 0) {
        file_put_contents(CACHE_PATH, implode("\n", $failed) . "\n", LOCK_EX);
    } else {
        @unlink(CACHE_PATH);
    }

    if ($ok > 0) hec_log('INFO  Flushed ' . $ok . ' cached payload(s).');
    return $ok;
}

// ---------------------------------------------------------------------------
// Telemetry Gathering
// ---------------------------------------------------------------------------

function gather_telemetry(): string
{
    $load = sys_getloadavg() ?: [0, 0, 0];
    
    $diskTotal = disk_total_space('/') ?: 0;
    $diskFree  = disk_free_space('/') ?: 0;
    $diskUsedPct = $diskTotal > 0 ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 2) : 0;
    
    $pagesize     = (int)(shell_exec('/sbin/sysctl -n hw.pagesize') ?? 0);
    $memFreePages = (int)(shell_exec('/sbin/sysctl -n vm.stats.vm.v_free_count') ?? 0);
    $memTotal     = (int)(shell_exec('/sbin/sysctl -n hw.physmem') ?? 0);
    $memUsed      = max(0, $memTotal - ($memFreePages * $pagesize));
    
    $pfStats = shell_exec('/sbin/pfctl -si 2>/dev/null') ?? '';
    preg_match('/current entries\s+(\d+)/', $pfStats, $mStates);
    $pfCurrent = isset($mStates[1]) ? (int)$mStates[1] : 0;
    
    $pfLimits = shell_exec('/sbin/pfctl -sm 2>/dev/null') ?? '';
    preg_match('/states\s+hard limit\s+(\d+)/', $pfLimits, $mMaxStates);
    $pfMax = isset($mMaxStates[1]) ? (int)$mMaxStates[1] : 0;
    
    $boottimeStr = shell_exec('/sbin/sysctl -n kern.boottime') ?? '';
    preg_match('/sec = (\d+)/', $boottimeStr, $mBoot);
    $uptime = isset($mBoot[1]) ? (time() - (int)$mBoot[1]) : 0;
    
    $event = [
        'cpu_load_1m'        => $load[0] ?? 0,
        'cpu_load_5m'        => $load[1] ?? 0,
        'cpu_load_15m'       => $load[2] ?? 0,
        'mem_total_bytes'    => $memTotal,
        'mem_used_bytes'     => $memUsed,
        'disk_root_used_pct' => $diskUsedPct,
        'pf_states_current'  => $pfCurrent,
        'pf_states_max'      => $pfMax,
        'uptime_seconds'     => $uptime
    ];
    
    return json_encode([
        'time'       => time(),
        'host'       => gethostname(),
        'source'     => 'opnsense:system',
        'sourcetype' => 'opnsense:telemetry:system',
        'event'      => $event
    ], JSON_UNESCAPED_SLASHES) . "\n";
}

// ---------------------------------------------------------------------------
// Daemon Loop
// ---------------------------------------------------------------------------

echo "INFO  Exporter daemon started.\n";
hec_log('INFO  Exporter daemon started.');

while (true) {
    echo "DEBUG: Reading INI file...\n";
    $ini = @parse_ini_file(CONF_PATH, true);
    if (!$ini) {
        echo "DEBUG: Failed to read INI. Sleeping 10s...\n";
        sleep(10);
        continue;
    }

    $cfg = $ini['splunk_hec'] ?? [];
    if (($cfg['enabled'] ?? '0') !== '1') {
        echo "INFO  Service disabled — exiting.\n";
        hec_log('INFO  Service disabled — exiting.');
        exit(0);
    }

    $token           = $cfg['token']    ?? '';
    $endpoint        = $cfg['endpoint'] ?? '';
    $verifySsl       = (($cfg['verify_ssl'] ?? '1') === '1');
    $useGzip         = (($cfg['use_gzip'] ?? '1') === '1');
    $enableTelemetry = (($cfg['enable_telemetry'] ?? '0') === '1');

    if ($token === '' || $endpoint === '') {
        echo "DEBUG: Token or Endpoint missing. Sleeping 10s...\n";
        sleep(10);
        continue;
    }

    $maxSizeMB = max(1, (int)($cfg['cache_size'] ?? 100));
    $maxAgeHrs = max(1, (int)($cfg['cache_time'] ?? 24));

    // Map the boolean settings from the UI to log paths and Splunk sourcetypes
    $logsCfg = $ini['logs'] ?? [];
    $sources = [];
    
    if (($logsCfg['system'] ?? '0') === '1')   $sources['/var/log/system/latest.log']   = 'opnsense:syslog';
    if (($logsCfg['filter'] ?? '0') === '1')   $sources['/var/log/filter/latest.log']   = 'opnsense:filterlog';
    if (($logsCfg['audit'] ?? '0') === '1')    $sources['/var/log/audit/latest.log']    = 'opnsense:audit';
    if (($logsCfg['dhcpd'] ?? '0') === '1')    $sources['/var/log/dhcpd/latest.log']    = 'opnsense:dhcpd';
    if (($logsCfg['lighttpd'] ?? '0') === '1') $sources['/var/log/lighttpd/latest.log'] = 'opnsense:lighttpd';
    if (($logsCfg['ntpd'] ?? '0') === '1')     $sources['/var/log/ntpd/latest.log']     = 'opnsense:ntpd';
    if (($logsCfg['openvpn'] ?? '0') === '1')  $sources['/var/log/openvpn/latest.log']  = 'opnsense:openvpn';
    if (($logsCfg['routing'] ?? '0') === '1')  $sources['/var/log/routing/latest.log']  = 'opnsense:routing';
    if (($logsCfg['suricata'] ?? '0') === '1') $sources['/var/log/suricata/latest.log'] = 'opnsense:suricata';
    if (($logsCfg['suricata_eve'] ?? '0') === '1') $sources['/var/log/suricata/eve.json'] = 'opnsense:suricata:eve';
    if (($logsCfg['unbound'] ?? '0') === '1')  $sources['/var/log/unbound/latest.log']  = 'opnsense:unbound';
    if (($logsCfg['kea'] ?? '0') === '1')      $sources['/var/log/kea/latest.log']      = 'opnsense:kea';
    if (($logsCfg['dnsmasq'] ?? '0') === '1')  $sources['/var/log/dnsmasq/latest.log']  = 'opnsense:dnsmasq';
    if (($logsCfg['wireguard'] ?? '0') === '1') $sources['/var/log/wireguard/latest.log'] = 'opnsense:wireguard';
    if (($logsCfg['portalauth'] ?? '0') === '1') $sources['/var/log/portalauth/latest.log'] = 'opnsense:portalauth';
    if (($logsCfg['crowdsec'] ?? '0') === '1') $sources['/var/log/crowdsec/latest.log'] = 'opnsense:crowdsec';
    if (($logsCfg['elasticsearch'] ?? '0') === '1') $sources['/var/log/elasticsearch/latest.log'] = 'opnsense:elasticsearch';

    // Zenarmor rapidly rotating IPDR spools
    if (($logsCfg['zenarmor'] ?? '0') === '1') {
        $ipdrFiles = glob('/usr/local/zenarmor/output/active/temp/*.ipdr');
        if (is_array($ipdrFiles)) {
            foreach ($ipdrFiles as $ipdr) {
                if (strpos($ipdr, '_alert_') !== false) $sources[$ipdr] = 'opnsense:zenarmor:alert';
                elseif (strpos($ipdr, '_dns_') !== false) $sources[$ipdr] = 'opnsense:zenarmor:dns';
                elseif (strpos($ipdr, '_http_') !== false) $sources[$ipdr] = 'opnsense:zenarmor:http';
                elseif (strpos($ipdr, '_tls_') !== false) $sources[$ipdr] = 'opnsense:zenarmor:tls';
                elseif (strpos($ipdr, '_conn_') !== false) $sources[$ipdr] = 'opnsense:zenarmor:conn';
                else $sources[$ipdr] = 'opnsense:zenarmor:traffic';
            }
        }
    }

    if (empty($sources)) {
        echo "DEBUG: No log sources enabled. Sleeping 10s...\n";
        sleep(10);
        continue;
    }

    echo "DEBUG: Flushing cache if any...\n";
    flush_cache($endpoint, $token, $maxSizeMB, $maxAgeHrs, $verifySsl, $useGzip);

    echo "DEBUG: Loading state...\n";
    $state = load_state();

    // Telemetry sampling (every 60 seconds)
    if ($enableTelemetry) {
        $lastTelemetryTime = $state['telemetry_time'] ?? 0;
        if ((time() - $lastTelemetryTime) >= 60) {
            echo "DEBUG: Gathering system telemetry...\n";
            $telemetryJson = gather_telemetry();
            $code = hec_post($endpoint, $token, $telemetryJson, $verifySsl, $useGzip);
            if ($code !== 200) {
                cache_payload($telemetryJson);
            } else {
                echo "INFO  Forwarded system telemetry.\n";
            }
            $state['telemetry_time'] = time();
        }
    }

    $anyActivity = false;

    foreach ($sources as $logFile => $sourcetype) {
        if (!is_readable($logFile)) {
            echo "DEBUG: Log file not readable: {$logFile}\n";
            continue;
        }

        $currentInode = fileinode($logFile);
        $prev         = $state[$logFile] ?? null;

        if ($prev !== null && (int)$prev['inode'] !== $currentInode) {
            echo "INFO  Log rotated: {$logFile}\n";
            hec_log('INFO  Log rotated: ' . $logFile);
            $prev = null;
        }

        $offset   = ($prev !== null) ? (int)$prev['offset'] : 0;
        $fileSize = filesize($logFile);

        if ($offset > $fileSize) {
            echo "INFO  File truncated: {$logFile}\n";
            hec_log('INFO  File truncated: ' . $logFile);
            $offset = 0;
        }

        if ($offset < $fileSize) {
            echo "DEBUG: Reading new lines from {$logFile}...\n";
            $fh = fopen($logFile, 'rb');
            if ($fh !== false) {
                fseek($fh, $offset);
                $lineCount = 0;
                $batchCount = 0;
                $processedCount = 0;
                $payloadBatch = '';
                $maxLinesPerSlice = 5000;

                while (($line = fgets($fh)) !== false) {
                    $processedCount++;
                    $line = rtrim($line, "\r\n");
                    if ($line === '' || $line === '{"index":{}}') continue;

                    // Support sending structured JSON (like eve.json) directly instead of as escaped strings
                    $eventData = $line;
                    if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                        $decoded = @json_decode($line, true);
                        if ($decoded !== null) {
                            $eventData = $decoded;
                        }
                    }

                    $payloadBatch .= json_encode([
                        'time'       => time(),
                        'host'       => gethostname(),
                        'source'     => $logFile,
                        'sourcetype' => $sourcetype,
                        'event'      => $eventData,
                    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";

                    $batchCount++;

                    // Send every 500 lines to avoid massive memory use or timeouts
                    if ($batchCount >= 500) {
                        $code = hec_post($endpoint, $token, $payloadBatch, $verifySsl, $useGzip);
                        if ($code === 200) {
                            $lineCount += $batchCount;
                            echo "."; // Progress indicator for massive files
                        } else {
                            cache_payload($payloadBatch);
                            echo "x";
                        }
                        $payloadBatch = '';
                        $batchCount = 0;
                    }
                    
                    // Yield to other log files if we've processed a huge chunk
                    if ($processedCount >= $maxLinesPerSlice) {
                        break;
                    }
                }

                // Send remaining batch
                if ($batchCount > 0) {
                    $code = hec_post($endpoint, $token, $payloadBatch, $verifySsl, $useGzip);
                    if ($code === 200) {
                        $lineCount += $batchCount;
                        echo ".";
                    } else {
                        cache_payload($payloadBatch);
                        echo "x";
                    }
                }

                if ($lineCount >= 500) echo "\n";

                $newOffset = ftell($fh);
                fclose($fh);

                $state[$logFile] = [
                    'inode'  => $currentInode,
                    'offset' => $newOffset,
                ];

                if ($lineCount > 0) {
                    $anyActivity = true;
                    $msg = "INFO  {$logFile}: forwarded {$lineCount} line(s).";
                    echo $msg . "\n";
                    hec_log($msg);
                }
            }
        } else {
            echo "DEBUG: No new lines in {$logFile}.\n";
        }
    }

    // Garbage collect deleted files (like ephemeral Zenarmor IPDRs) from state
    foreach (array_keys($state) as $cachedFile) {
        if ($cachedFile === 'telemetry_time') continue;
        if (!file_exists($cachedFile)) {
            unset($state[$cachedFile]);
        }
    }

    save_state($state);
    
    if ($anyActivity) {
        // If we are actively chewing through backlogs, yield CPU briefly but skip the 10s sleep
        usleep(100000); // 100ms
    } else {
        echo "DEBUG: Sleeping for 10 seconds...\n";
        sleep(10);
    }
}
