<?php

/*
 * Copyright (C) 2025 Cedrik Pischem
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

function ndpproxy_services()
{
    global $config;

    $services = [];

    if (
        isset($config['OPNsense']['ndpproxy']['general']['enabled']) &&
        $config['OPNsense']['ndpproxy']['general']['enabled'] == 1
    ) {
        $services[] = [
            'description' => gettext('NDP Proxy'),
            'configd' => [
                'start' => ['ndp-proxy-go start'],
                'restart' => ['ndp-proxy-go restart'],
                'stop' => ['ndp-proxy-go stop'],
            ],
            'name' => 'ndpproxy',
            'pidfile' => '/var/run/ndp-proxy-go.pid'
        ];
    }

    return $services;
}

function ndpproxy_xmlrpc_sync()
{
    $result = [];

    $result[] = array(
        'description' => gettext('NDP Proxy'),
        'section' => 'OPNsense.ndpproxy',
        'id' => 'ndpproxy',
        'services' => ["ndpproxy"],
    );

    return $result;
}
