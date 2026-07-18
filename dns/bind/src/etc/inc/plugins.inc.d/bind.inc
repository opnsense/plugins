<?php

/*
 * Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
 * Copyright (C) 2023 Franco Fichtner <franco@opnsense.org>
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

function bind_enabled()
{
    $model = new \OPNsense\Bind\General();
    return (string)$model->enabled == '1';
}

function bind_configure()
{
    return [
        'bind_start' => ['bind_configure_do'],
        'dns' => ['bind_configure_do'],
    ];
}

function bind_services()
{
    $services = [];

    if (!bind_enabled()) {
        return $services;
    }

    $model = new \OPNsense\Bind\General();

    $services[] = [
        /* the port may still be something other than 53, but it's safe to register a conflict for it */
        'dns_ports' => ((string)$model->active_interface === '' ? [(string)$model->port] : []),
        'description' => gettext('BIND Daemon'),
        'configd' => [
            'restart' => ['bind restart'],
            'start' => ['bind start'],
            'stop' => ['bind stop'],
        ],
        'php' => ['stop' => ['bind_service_stop']],
        'pidfile' => '/var/run/named/pid',
        'name' => 'named',
    ];

    return $services;
}

function bind_xmlrpc_sync()
{
    $result = [];

    $result['id'] = 'bind';
    $result['section'] = 'OPNsense.bind';
    $result['description'] = gettext('BIND domain name service');
    $result['services'] = ['named'];

    return [$result];
}

function bind_service_stop()
{
    killbypid('/var/run/bind_dhcplease.pid');
}

function bind_configure_do($verbose)
{
    if (!bind_enabled()) {
        configd_run('bind stop');
        return;
    }

    service_log('Starting BIND...', $verbose);

    /*
     * Stop named and the lease watcher before regenerating primary zone
     * files. The stop action removes journals for watcher-managed zones,
     * preventing a newly rendered base zone from being paired with an older
     * dynamic-update journal.
     */
    configd_run('bind stop');
    configd_run('template reload OPNsense/Bind');
    bind_generate_listen_config();
    mwexecf('/usr/local/opnsense/scripts/OPNsense/Bind/bindStart.sh');

    service_log("done.\n", $verbose);
}

/**
 * Render BIND listener directives from the selected interface identifiers.
 */
function bind_generate_listen_config()
{
    $model = new \OPNsense\Bind\General();
    $interfaces = array_filter(explode(',', (string)$model->active_interface));
    $filename = '/usr/local/etc/namedb/named.conf';
    $legacyFilename = '/usr/local/etc/namedb/named.conf.d/bind-listen.conf';
    $marker = '        # bind-listener-directives';

    if (empty($interfaces)) {
        @unlink($legacyFilename);
        return;
    }

    array_unshift($interfaces, 'lo0');
    $addresses = ['inet' => [], 'inet6' => []];
    foreach (interfaces_addresses($interfaces) as $address => $info) {
        if (($info['name'] === 'lo0' && $info['family'] === 'inet' && $address !== '127.0.0.1') || !$info['bind']) {
            continue;
        }
        $addresses[$info['family']][] = $address;
    }

    $listen = [];
    $listen[] = sprintf(
        'listen-on port %s { %s; };',
        (string)$model->port,
        empty($addresses['inet']) ? 'none' : implode('; ', array_unique($addresses['inet']))
    );
    $listen[] = sprintf(
        'listen-on-v6 port %s { %s; };',
        (string)$model->port,
        empty($addresses['inet6']) ? 'none' : implode('; ', array_unique($addresses['inet6']))
    );

    $configuration = @file_get_contents($filename);
    if ($configuration === false || strpos($configuration, $marker) === false) {
        error_log('BIND listener configuration marker is missing');
        return;
    }

    file_put_contents($filename, str_replace($marker, implode("\n", $listen), $configuration));
    @unlink($legacyFilename);
}

/**
 *  register syslog facilities
 * @return array
 */
function bind_cron()
{
    return [];
}

function bind_syslog()
{
    $syslogconf = [];

    $syslogconf['bind'] = ['facility' => ['named', 'bind-dhcplease']];

    return $syslogconf;
}
