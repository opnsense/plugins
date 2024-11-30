<?php

/*
 * Copyright © 2017 Fabian Franz
 * Copyright (c) 2024 Cedrik Pischem
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
 * THIS SOFTWARE IS PROVIDED ``AS IS`` AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

function mdnsrepeater_services()
{
    global $config;

    $services = [];

    if (isset($config['OPNsense']['MDNSRepeater']['enabled']) &&
        $config['OPNsense']['MDNSRepeater']['enabled'] == '1') {
        $services[] = [
            'description' => gettext('mDNS Repeater'),
            'configd' => [
                'start' => ['mdnsrepeater start'],
                'restart' => ['mdnsrepeater restart'],
                'stop' => ['mdnsrepeater stop'],
            ],
            'name' => 'mdns-repeater',
            'pidfile' => '/var/run/mdns-repeater.pid',
        ];
    }

    return $services;
}

function mdnsrepeater_xmlrpc_sync()
{
    $result = [];

    $result[] = [
        'description' => gettext('mDNS Repeater'),
        'section' => 'OPNsense.MDNSRepeater',
        'id' => 'mdns-repeater',
        'services' => ['mdns-repeater'],
    ];

    return $result;
}
