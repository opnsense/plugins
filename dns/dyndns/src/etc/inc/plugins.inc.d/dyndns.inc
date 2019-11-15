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

require_once('plugins.inc.d/dyndns/phpDynDNS.inc');
require_once('plugins.inc.d/dyndns/r53.inc');

function dyndns_configure()
{
    return array(
        'bootup' => array('dyndns_configure_do'),
        'local' => array('dyndns_configure_do'),
        'newwanip' => array('dyndns_configure_do:2'),
    );
}

function dyndns_enabled()
{
    global $config;

    if (isset($config['dyndnses']['dyndns'])) {
        foreach ($config['dyndnses']['dyndns'] as $conf) {
            if (isset($conf['enable'])) {
                return true;
            }
        }
    }

    return false;
}

function dyndns_services()
{
    global $config;

    $services = array();

    if (dyndns_enabled()) {
        $services[] = array(
            'description' => gettext('Dynamic DNS'),
            'configd' => array(
                'restart' => array('dyndns reload'),
            ),
            'nocheck' => true,
            'name' => 'dyndns',
        );
    }

    return $services;
}

function dyndns_cron()
{
    $jobs = array();

    if (dyndns_enabled()) {
        $jobs[]['autocron'] = array('/usr/local/etc/rc.dyndns', '11', '1');
    }

    return $jobs;
}

function dyndns_list()
{
    /*
     * XXX something like this would be cool:
     *
     * https://github.com/openwrt/packages/blob/master/net/ddns-scripts/files/services
     */

    return array(
        '3322' => '3322',
        'citynetwork' => 'City Network',
        'cloudflare' => 'CloudFlare',
        'cloudflare-v6' => 'CloudFlare (v6)',
        'custom' => 'Custom',
        'custom-v6' => 'Custom (v6)',
        'dhs' => 'DHS',
        'digitalocean' => 'DigitalOcean',
        'dnsexit' => 'DNSexit',
        'dnsomatic' => 'DNS-O-Matic',
        'duckdns' => 'Duck DNS',
        'dyndns' => 'DynDNS (dynamic)',
        'dyndns-custom' => 'DynDNS (custom)',
        'dyndns-static' => 'DynDNS (static)',
        'dyns' => 'DyNS',
        'easydns' => 'easyDNS',
        'eurodns' => 'EuroDNS',
        'freedns' => 'freeDNS',
        'googledomains' => 'Google Domains',
        'gratisdns' => 'GratisDNS',
        'he-net' => 'HE.net',
        'he-net-tunnelbroker' => 'HE.net Tunnelbroker',
        'he-net-v6' => 'HE.net (v6)',
        'linode' => 'Linode',
        'linode-v6' => 'Linode (v6)',
        'loopia' => 'Loopia',
        'namecheap' => 'Namecheap',
        'noip' => 'No-IP',
        'noip-free' => 'No-IP (free)',
        'ods' => 'ODS.org',
        'oray' => 'Oray',
        'ovh-dynhost' => 'OVH DynHOST',
        'regfish' => 'regfish',
        'regfish-v6' => 'regfish (v6)',
        'route53' => 'Route 53',
        'route53-v6' => 'Route 53 (v6)',
        'selfhost' => 'SelfHost',
        'strato' => 'STRATO',
        'zoneedit' => 'ZoneEdit',
    );
}

function dyndns_cache_file($conf, $ipver = 4)
{
    $ipver = $ipver == 6 ? '_v6' : '';

    return "/var/cache/dyndns_{$conf['interface']}_{$conf['host']}_{$conf['id']}{$ipver}.cache";
}

function dyndns_configure_client($conf)
{
    if (!isset($conf['enable'])) {
        return;
    }

    $dns = new updatedns(
        $dnsService = $conf['type'],
        $dnsHost = $conf['host'],
        $dnsUser = $conf['username'],
        $dnsPass = $conf['password'],
        $dnsWilcard = $conf['wildcard'],
        $dnsMX = $conf['mx'],
        $dnsIf = "{$conf['interface']}",
        $dnsBackMX = null,
        $dnsServer = null,
        $dnsPort = null,
        $dnsUpdateURL = "{$conf['updateurl']}",
        $forceUpdate = $conf['force'],
        $dnsZoneID = $conf['zoneid'],
        $dnsTTL = $conf['ttl'],
        $dnsResultMatch = "{$conf['resultmatch']}",
        $dnsRequestIf = "{$conf['requestif']}",
        $dnsID = "{$conf['id']}",
        $dnsVerboseLog = $conf['verboselog'],
        $curlIpresolveV4 = $conf['curl_ipresolve_v4'],
        $curlSslVerifypeer = $conf['curl_ssl_verifypeer']
    );
}

function dyndns_configure_do($verbose = false, $int = '')
{
    global $config;

    if (!dyndns_enabled()) {
        return;
    }

    $dyndnscfg = $config['dyndnses']['dyndns'];
    $gwgroups = return_gateway_groups_array();

    if ($verbose) {
        echo 'Configuring dynamic DNS clients...';
        flush();
    }

    foreach ($dyndnscfg as $dyndns) {
        if ((empty($int)) || ($int == $dyndns['interface']) || (is_array($gwgroups[$dyndns['interface']]))) {
            $dyndns['verboselog'] = isset($dyndns['verboselog']);
            $dyndns['curl_ipresolve_v4'] = isset($dyndns['curl_ipresolve_v4']);
            $dyndns['curl_ssl_verifypeer'] = isset($dyndns['curl_ssl_verifypeer']);
            dyndns_configure_client($dyndns);
            sleep(1);
        }
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function dyndns_failover_interface($interface, $family = 'all')
{
    global $config;

    /* shortcut for known interfaces */
    if (isset($config['interfaces'][$interface])) {
        return get_real_interface($interface, $family);
    }

    /* compare against gateway groups */
    $a_groups = return_gateway_groups_array();
    if (isset($a_groups[$interface])) {
        /* we found a gateway group, fetch the interface or vip */
        if ($a_groups[$interface][0]['vip'] != '') {
            return $a_groups[$interface][0]['vip'];
        } else {
            return $a_groups[$interface][0]['int'];
        }
    }

    /* fall through to get real interface the hard way */
    return get_real_interface($interface, $family);
}
