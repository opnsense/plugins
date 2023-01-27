<?php

/*
 * Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
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

function wireguard_enabled()
{
    $model = new \OPNsense\Wireguard\General();
    return (string)$model->enabled == '1';
}

function wireguard_services()
{
    $services = [];

    if (!wireguard_enabled()) {
        return $services;
    }

    $service = [
        'description' => gettext('WireGuard VPN'),
        'configd' => [
            'restart' => ['wireguard restart'],
            'start' => ['wireguard start'],
            'stop' => ['wireguard stop'],
        ],
        'name' => 'wireguard-go',
    ];

    if (file_exists('/boot/modules/if_wg.ko') || file_exists('/boot/kernel/if_wg.ko')) {
        $service['name'] = 'wireguard';
        $service['nocheck'] = true;
    }

    $services[] = $service;

    return $services;
}

function wireguard_interfaces()
{
    $interfaces = [];

    if (!wireguard_enabled()) {
        return $interfaces;
    }

    $interfaces['wireguard'] = [
        'descr' => gettext('WireGuard (Group)'),
        'if' => 'wireguard',
        'virtual' => true,
        'enable' => true,
        'type' => 'group',
        'networks' => [],
    ];

    return $interfaces;
}

function wireguard_xmlrpc_sync()
{
    $result = [];

    $result['id'] = 'wireguard';
    $result['section'] = 'OPNsense.wireguard';
    $result['description'] = gettext('WireGuard');
    $result['services'] = ['wireguard-go'];

    if (file_exists('/boot/modules/if_wg.ko') || file_exists('/boot/kernel/if_wg.ko')) {
        $result['services'] = ['wireguard'];
    }

    return [$result];
}

function wireguard_devices()
{
    return [['pattern' => '^wg', 'volatile' => true]];
}
