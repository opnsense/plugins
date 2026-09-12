<?php

/*
 * Copyright (C) 2026 VEQNORA
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

function pppoe_server_enabled()
{
    $model = new \OPNsense\PPPoEServer\PPPoEServer();

    return (string)$model->general->enabled == '1';
}

function pppoe_server_services()
{
    $services = [];

    if (pppoe_server_enabled()) {
        $services[] = [
            'description' => gettext('PPPoE Server'),
            'configd' => [
                'restart' => ['pppoe_server restart'],
                'start' => ['pppoe_server start'],
                'stop' => ['pppoe_server stop'],
            ],
            'name' => 'pppoe_server',
            'pidfile' => '/var/run/pppoe_server.pid',
        ];

        $model = new \OPNsense\PPPoEServer\PPPoEServer();
        if ((string)$model->radius->coa->enabled == '1') {
            $services[] = [
                'description' => gettext('PPPoE CoA listener'),
                'configd' => [
                    'restart' => ['pppoe_server coa_restart'],
                    'start' => ['pppoe_server coa_start'],
                    'stop' => ['pppoe_server coa_stop'],
                ],
                'name' => 'pppoe_server_coa',
                'pidfile' => '/var/run/pppoe_server_coa.pid',
            ];
        }
    }

    return $services;
}

/**
 * expose the plugin configuration section for HA XMLRPC config sync
 */
function pppoe_server_xmlrpc_sync()
{
    $result = [];

    $result[] = [
        'description' => gettext('PPPoE Server'),
        'section' => 'OPNsense.pppoeserver',
        'id' => 'pppoeserver',
        'services' => ['pppoe_server'],
    ];

    return $result;
}

/**
 * dedicated syslog target for session events emitted by link scripts
 */
function pppoe_server_syslog()
{
    $logfacilities = [];

    $logfacilities['pppoe'] = ['facility' => ['pppoe']];

    return $logfacilities;
}

/**
 * expose the client networks as a virtual interface group so the
 * administrator can attach firewall and NAT rules to it
 */
function pppoe_server_interfaces()
{
    $interfaces = [];

    if (!pppoe_server_enabled()) {
        return $interfaces;
    }

    $model = new \OPNsense\PPPoEServer\PPPoEServer();

    $networks = [];
    foreach ($model->pools->pool->iterateItems() as $pool) {
        if ((string)$pool->enabled != '1') {
            continue;
        }
        $start = ip2long((string)$pool->start);
        $end = ip2long((string)$pool->end);
        if ($start === false || $end === false) {
            continue;
        }
        /* smallest common prefix covering the pool range */
        $mask = 32;
        while ($mask > 0 && (($start & (-1 << (32 - $mask))) != ($end & (-1 << (32 - $mask))))) {
            $mask--;
        }
        $networks[] = [
            'network' => long2ip($start & (-1 << (32 - $mask))),
            'mask' => $mask,
        ];
    }

    if (count($networks)) {
        $interfaces['pppoe'] = [
            'enable' => true,
            'virtual' => true,
            'networks' => $networks,
            'if' => 'pppoe',
            'descr' => 'PPPoE Clients',
            'type' => 'group',
        ];
    }

    return $interfaces;
}
