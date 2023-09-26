<?php

/*
 * Copyright (C) 2014-2017 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2010 Ermal Luçi
 * Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

function rfc2136_configure()
{
    return [
        'bootup' => ['rfc2136_configure_do'],
        'local' => ['rfc2136_configure_do'],
        'newwanip' => ['rfc2136_configure_do:2'],
    ];
}

function rfc2136_enabled()
{
    global $config;

    if (isset($config['dnsupdates']['dnsupdate'])) {
        foreach ($config['dnsupdates']['dnsupdate'] as $dnsupdate) {
            if (isset($dnsupdate['enable'])) {
                return true;
            }
        }
    }

    return false;
}

function rfc2136_services()
{
    $services = [];

    if (rfc2136_enabled()) {
        $services[] = [
            'description' => gettext('RFC 2136'),
            'configd' => [ 'restart' => ['rfc2136 reload'] ],
            'nocheck' => true,
            'name' => 'rfc2136',
        ];
    }

    return $services;
}

function rfc2136_cron()
{
    $jobs = [];

    if (rfc2136_enabled()) {
        $jobs[]['autocron'] = ['/usr/local/etc/rc.rfc2136', '16', '1'];
    }

    return $jobs;
}

function rfc2136_cache_file($dnsupdate, $ipver = 4)
{
    $ipver = $ipver == 6 ? '_v6' : '';

    return "/var/cache/rfc2136_{$dnsupdate['interface']}_{$dnsupdate['host']}_{$dnsupdate['server']}{$ipver}.cache";
}

function rfc2136_configure_do($verbose = false, $int = '', $updatehost = '', $forced = false)
{
    global $config;

    if (!rfc2136_enabled()) {
        return;
    }

    service_log('Configuring RFC 2136 clients...', $verbose);

    foreach ($config['dnsupdates']['dnsupdate'] as $i => $dnsupdate) {
        if (!isset($dnsupdate['enable'])) {
            continue;
        } elseif (!empty($int) && $int != $dnsupdate['interface']) {
            continue;
        } elseif (!empty($updatehost) && ($updatehost != $dnsupdate['host'])) {
            continue;
        }

        $currentTime = time();

        $keyname = $dnsupdate['keyname'];
        /* trailing dot */
        if (substr($keyname, -1) != ".") {
            $keyname .= ".";
        }

        $hostname = $dnsupdate['host'];
        /* trailing dot */
        if (substr($hostname, -1) != ".") {
            $hostname .= ".";
        }

        $keyfile = "/var/etc/nsupdatekey{$i}";
        $keyalgo = !empty($dnsupdate['keyalgo']) ? $dnsupdate['keyalgo'] : 'hmac-md5';
        $keyfill = [
            "key \"{$keyname}\" {",
            "\talgorithm {$keyalgo};",
            "\tsecret \"{$dnsupdate['keydata']}\";",
            "};",
            '' /* end of file */
        ];

        file_put_contents($keyfile, implode(PHP_EOL, $keyfill));

        /* generate update instructions */
        $upinst = "";
        if (!empty($dnsupdate['server'])) {
            $upinst .= "server {$dnsupdate['server']}\n";
        }

        $maxCacheAgeSecs = 25 * 24 * 60 * 60;
        $need_update = false;

        if (empty($dnsupdate['recordtype']) || $dnsupdate['recordtype'] == 'A') {
            $cacheFile = rfc2136_cache_file($dnsupdate, 4);
            if (file_exists($cacheFile)) {
                list($cachedipv4, $cacheTimev4) = explode('|', file_get_contents($cacheFile));
            } else {
                list($cachedipv4, $cacheTimev4) = ['', ''];
            }
            if (isset($dnsupdate['usepublicip'])) {
                $wanip = get_rfc2136_ip_address($dnsupdate['interface'], 4);
            } else {
                list ($wanip) = interfaces_primary_address($dnsupdate['interface']);
            }
            if (is_ipaddrv4($wanip)) {
                if (($wanip != $cachedipv4) || (($currentTime - $cacheTimev4) > $maxCacheAgeSecs) || $forced) {
                    $upinst .= "update delete {$dnsupdate['host']}. A\n";
                    $upinst .= "update add {$dnsupdate['host']}. {$dnsupdate['ttl']} A {$wanip}\n";
                    @file_put_contents($cacheFile, "{$wanip}|{$currentTime}");
                    log_error("Dynamic DNS: updating cache file {$cacheFile}: {$wanip}");
                    $need_update = true;
                } else {
                    log_error("Dynamic DNS: Not updating {$dnsupdate['host']} A record because the IP address has not changed.");
                }
            } else {
                @unlink($cacheFile);
            }
        }

        if (empty($dnsupdate['recordtype']) || $dnsupdate['recordtype'] == 'AAAA') {
            $cacheFile6 = rfc2136_cache_file($dnsupdate, 6);
            if (file_exists($cacheFile6)) {
                list($cachedipv6, $cacheTimev6) = explode('|', file_get_contents($cacheFile6));
            } else {
                list($cachedipv6, $cacheTimev6) = ['', ''];
            }
            if (isset($dnsupdate['usepublicip'])) {
                $wanipv6 = get_rfc2136_ip_address($dnsupdate['interface'], 6);
            } else {
                list ($wanipv6) = interfaces_primary_address6($dnsupdate['interface']);
            }
            if (is_ipaddrv6($wanipv6)) {
                if (($wanipv6 != $cachedipv6) || (($currentTime - $cacheTimev6) > $maxCacheAgeSecs) || $forced) {
                    $upinst .= "update delete {$dnsupdate['host']}. AAAA\n";
                    $upinst .= "update add {$dnsupdate['host']}. {$dnsupdate['ttl']} AAAA {$wanipv6}\n";
                    @file_put_contents($cacheFile6, "{$wanipv6}|{$currentTime}");
                    log_error("Dynamic DNS: updating cache file {$cacheFile6}: {$wanipv6}");
                    $need_update = true;
                } else {
                    log_error("Dynamic DNS: Not updating {$dnsupdate['host']} AAAA record because the IPv6 address has not changed.");
                }
            } else {
                @unlink($cacheFile6);
            }
        }

        $upinst .= "\n";  /* mind that trailing newline! */

        if ($need_update) {
            @file_put_contents("/var/etc/nsupdatecmds{$i}", $upinst);
            unset($upinst);
            /* invoke nsupdate */
            $cmd = "/usr/local/bin/nsupdate -k {$keyfile}";
            if (isset($dnsupdate['usetcp'])) {
                $cmd .= " -v";
            }
            $cmd .= " /var/etc/nsupdatecmds{$i}";
            mwexec_bg($cmd);
            unset($cmd);
        }
    }

    service_log("done.\n", $verbose);
}

function get_rfc2136_ip_address($int, $ipver = 4)
{
    list ($ip_address) = $ipver == 6 ? interfaces_primary_address6($int) : interfaces_primary_address($int);
    if (empty($ip_address)) {
        log_error("Aborted IPv{$ipver} detection: no address for {$int}");
        return 'down';
    }

    if ($ipver != 6 && is_private_ip($ip_address)) {
        $ip_ch = curl_init('http://checkip.dyndns.org');
        curl_setopt($ip_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ip_ch, CURLOPT_INTERFACE, $ip_address);
        curl_setopt($ip_ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ip_ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $ip_result = curl_exec($ip_ch);
        if ($ip_result !== false) {
            preg_match('=<body>Current IP Address: (.*)</body>=siU', $ip_result, $matches);
            $ip_address = trim($matches[1]);
        } else {
            log_error('Aborted IPv4 detection: ' . curl_error($ip_ch));
            $ip_address = '';
        }
        curl_close($ip_ch);
    } elseif ($ipver == 6 && is_linklocal($ip_address)) {
        log_error('Aborted IPv6 detection: cannot bind to link-local address');
        $ip_address = '';
    }

    if (($ipver == 6 && !is_ipaddrv6($ip_address)) || ($ipver != 6 && !is_ipaddrv4($ip_address))) {
        return 'down';
    }

    return $ip_address;
}
