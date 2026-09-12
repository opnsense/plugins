<?php

/**
 *    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Register HCloudDNS services
 * @return array
 */
function hclouddns_services()
{
    $services = array();

    $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
    if ((string)$mdl->general->enabled == '1') {
        $services[] = array(
            'description' => gettext('Hetzner Cloud Dynamic DNS'),
            'configd' => array(
                'start' => array('hclouddns start'),
                'stop' => array('hclouddns stop'),
                'restart' => array('hclouddns update'),
            ),
            'name' => 'hclouddns',
            'nocheck' => true,
        );
    }

    return $services;
}

/**
 * Register cron jobs for HCloudDNS
 * Only active when explicitly enabled - automatic triggers (gateway syshook, newwanip)
 * handle most use cases without needing scheduled updates.
 * @return array
 */
function hclouddns_cron()
{
    $jobs = [];

    $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
    // Cron is only registered when both service AND cron are enabled
    if ((string)$mdl->general->enabled == '1' && (string)$mdl->general->cronEnabled == '1') {
        // Use cronInterval setting (in minutes) - cast to string first as model fields are objects
        $minutes = intval((string)$mdl->general->cronInterval);
        if (empty($minutes) || $minutes < 1) {
            $minutes = 5;  // Default 5 minutes
        }
        if ($minutes > 60) {
            $minutes = 60;
        }

        // autocron format: [command, minute, hour, monthday, month, weekday]
        $jobs[]['autocron'] = [
            '/usr/local/sbin/configctl hclouddns update',
            "*/{$minutes}"
        ];
    }

    return $jobs;
}

/**
 * Register plugin hooks - triggers on interface IP changes
 * @return array
 */
function hclouddns_configure()
{
    return [
        'newwanip' => ['hclouddns_configure_do:2'],
    ];
}

/**
 * Called when WAN IP changes - trigger DNS update
 * @param bool $verbose
 */
function hclouddns_configure_do($verbose = false)
{
    $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
    if ((string)$mdl->general->enabled != '1') {
        return;
    }

    service_log('Hetzner Cloud DDNS: Interface IP changed, updating DNS...', $verbose);

    // Trigger update via configd
    configd_run('hclouddns update');

    service_log("done.\n", $verbose);
}

/**
 * Register syslog facility
 * @return array
 */
function hclouddns_syslog()
{
    $logfacilities = [];
    $logfacilities['hclouddns'] = ['facility' => ['hclouddns']];
    return $logfacilities;
}

/**
 * XML-RPC sync handler
 * @return array
 */
function hclouddns_xmlrpc_sync()
{
    $result = array();
    $result['id'] = 'hclouddns';
    $result['section'] = 'OPNsense.HCloudDNS';
    $result['description'] = gettext('Hetzner Cloud Dynamic DNS');
    return array($result);
}
