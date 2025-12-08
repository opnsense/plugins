<?php

/**
 *    Copyright (C) 2025 C. Hall (chall37@users.noreply.github.com)
 *
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
 *
 */

/**
 * Check if the dnsmasq to unbound service is enabled
 * @return bool
 */
function dnsmasqtounbound_enabled()
{
    $model = new \OPNsense\DnsmasqToUnbound\DnsmasqToUnbound();
    return (string)$model->enabled == '1';
}

/**
 * Register dnsmasq to unbound service for the dashboard widget
 * @return array
 */
function dnsmasqtounbound_services()
{
    $services = [];

    if (!dnsmasqtounbound_enabled()) {
        return $services;
    }

    $services[] = [
        'description' => gettext('Dnsmasq to Unbound Watcher'),
        'configd' => [
            'restart' => ['dnsmasqtounbound restart'],
            'start' => ['dnsmasqtounbound start'],
            'stop' => ['dnsmasqtounbound stop'],
        ],
        'pidfile' => '/var/run/dnsmasq_watcher.pid',
        'name' => 'dnsmasq_watcher',
    ];

    return $services;
}

/**
 * Register configuration sections for HA sync
 * @return array
 */
function dnsmasqtounbound_xmlrpc_sync()
{
    $result = [];
    $result['id'] = 'dnsmasqtounbound';
    $result['section'] = 'OPNsense.DnsmasqToUnbound';
    $result['description'] = gettext('Dnsmasq to Unbound');
    $result['services'] = ['dnsmasq_watcher'];
    return [$result];
}

/**
 * Register syslog facility
 * @return array
 */
function dnsmasqtounbound_syslog()
{
    $syslogconf = [];
    $syslogconf['dnsmasq_watcher'] = ['facility' => ['dnsmasq_watcher']];
    return $syslogconf;
}
