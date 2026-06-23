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

function dnscryptproxy_enabled()
{
    $model = new \OPNsense\Dnscryptproxy\General();
    return (string)$model->enabled == '1';
}

function dnscryptproxy_configure()
{
    return [
        'dns' => ['dnscryptproxy_configure_do'],
    ];
}

function dnscryptproxy_services()
{
    $services = [];

    if (!dnscryptproxy_enabled()) {
        return $services;
    }

    $model = new \OPNsense\Dnscryptproxy\General();
    $ports = [];

    /* DNS service is eligable for core use when either 0.0.0.0 or :: are set */
    foreach (explode(',', (string)$model->listen_addresses) as $addrport) {
        if (preg_match('/^0\.0\.0\.0:([\d]+)$/', $addrport, $matches)) {
            $ports[$matches[1]] = 1;
        } elseif (preg_match('/^\[::\]:([\d]+)$/', $addrport, $matches)) {
            $ports[$matches[1]] = 1;
        }
    }

    $services[] = [
        /* the port may still be something other than 53, but it's safe to register a conflict for it */
        'dns_ports' => array_keys($ports),
        'description' => gettext('DNSCrypt-Proxy'),
        'configd' => [
            'restart' => ['dnscryptproxy restart'],
            'start' => ['dnscryptproxy start'],
            'stop' => ['dnscryptproxy stop'],
        ],
        'pid' => '/var/run/dnscrypt-proxy.pid',
        'name' => 'dnscrypt-proxy',
    ];

    return $services;
}

function dnscryptproxy_configure_do($verbose)
{
    service_log('Starting DNSCrypt-Proxy...', $verbose);

    configd_run('template reload OPNsense/Dnscryptproxy');
    configd_run('dnscryptproxy restart');

    service_log("done.\n", $verbose);
}
