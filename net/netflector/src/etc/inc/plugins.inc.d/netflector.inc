<?php

/*
 * Copyright (C) 2026 Sergii Bogomolov
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

/**
 * Registers the daemon with OPNsense's service machinery, so it appears under Services and can be
 * started, stopped and reported on from the GUI.
 */

function netflector_enabled()
{
    $model = new \OPNsense\Netflector\Netflector();

    if (!$model->general->enabled->isEqual('1')) {
        return false;
    }

    // The daemon refuses to start with no reflector to run, so the service is only "on" once an entry
    // is enabled too. Otherwise the GUI would report a service that cannot come up.
    foreach ($model->reflectors->reflector->iterateItems() as $entry) {
        if ($entry->enabled->isEqual('1')) {
            return true;
        }
    }

    return false;
}

/**
 * Registers the daemon's syslog tag as an application, so remote syslog destinations (System >
 * Settings > Logging) can forward exactly its lines. The rc script starts the daemon under
 * `daemon -S -T netflector`, so every line carries this tag; the name here must match that -T,
 * as must the Syslog/local template filter that routes the tag into /var/log/netflector/.
 */
function netflector_syslog()
{
    return ['netflector' => ['facility' => ['netflector']]];
}

function netflector_xmlrpc_sync()
{
    return [[
        'description' => gettext('Netflector'),
        'help' => gettext(
            'Synchronize the netflector configuration to the other HA host. Include Virtual IPs as ' .
            'well if a CARP dependency is set: it is stored as the virtual IP\'s identifier, which ' .
            'only means anything on a host that has that virtual IP.'
        ),
        'id' => 'netflector',
        'section' => 'OPNsense.Netflector',
        'services' => ['netflector'],
    ]];
}

function netflector_services()
{
    $services = [];

    if (!netflector_enabled()) {
        return $services;
    }

    $services[] = [
        'description' => gettext('Netflector'),
        'name' => 'netflector',
        'pidfile' => '/var/run/netflector.pid',
        'configd' => [
            'restart' => ['netflector restart'],
            'start' => ['netflector start'],
            'stop' => ['netflector stop'],
        ],
    ];

    return $services;
}
