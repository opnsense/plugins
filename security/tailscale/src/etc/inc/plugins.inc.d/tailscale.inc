<?php

/*
 * Copyright (C) 2024 Sheridan Computers
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

function tailscale_enabled()
{
    return !empty((string)(new \OPNsense\Tailscale\Settings())->enabled);
}

function tailscale_network_ready()
{
    global $config;

    if (!tailscale_enabled()) {
        return true;
    }

    $device = 'tailscale0';
    $interface = convert_real_interface_to_friendly_interface_name($device);
    if (empty($interface) || !isset($config['interfaces'][$interface]['enable'])) {
        return true;
    }

    $actions = ['inet' => 'newip', 'inet6' => 'newipv6'];
    $families = [];
    /* Include inactive gateways so a missing device does not hide a readiness failure. */
    foreach ((new \OPNsense\Routing\Gateways())->gatewaysIndexedByName(false, false, true) as $gateway) {
        if ($gateway['interface'] != $interface || !empty($gateway['monitor_disable'])) {
            continue;
        }
        $family = $gateway['ipprotocol'];
        if (isset($actions[$family]) && ($family != 'inet6' || is_ipv6_allowed())) {
            $families[$family] = true;
        }
    }

    if (empty($families)) {
        return true;
    }

    $details = legacy_interfaces_details($device);
    $success = true;
    foreach ($families as $family => $unused) {
        $version = $family == 'inet' ? 'IPv4' : 'IPv6';
        $address = $family == 'inet' ? interfaces_primary_address($interface, $details) :
            interfaces_primary_address6($interface, $details);
        $valid = $family == 'inet' ? is_ipaddrv4($address[0]) :
            is_ipaddrv6($address[0]) && !is_linklocal($address[0]);
        if (!$valid || $address[3] != $device) {
            log_msg("Tailscale: no usable {$version} address on {$device}; network initialization not queued.", LOG_ERR);
            $success = false;
            continue;
        }

        try {
            /* Force renewal even when restarting the device with an unchanged address. */
            $request = trim(configdp_run('interface ' . $actions[$family], [$device, 'force'], true));
        } catch (\Exception $e) {
            $request = '';
        }
        /* Detached configd requests acknowledge submission with a UUID, not completion. */
        if (!preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/iD', $request)) {
            log_msg("Tailscale: failed to queue {$version} network initialization for {$device}.", LOG_ERR);
            $success = false;
            continue;
        }
        log_msg("Tailscale: queued {$version} network initialization for {$device} (request {$request}).", LOG_INFO);
    }

    return $success;
}

function tailscale_services()
{
    $services = [];

    if (!tailscale_enabled()) {
        return $services;
    }

    $services[] = [
        'description' => gettext('Tailscale'),
        'pidfile' => '/var/run/tailscaled.pid',
        'configd' => [
            'restart' => ['tailscale restart'],
            'start' => ['tailscale start'],
            'stop' => ['tailscale stop'],
        ],
        'name' => 'tailscale',
    ];

    return $services;
}

function tailscale_devices()
{
    return [[
        'pattern' => '^tailscale0$',
        'configurable' => false,
        'spoofmac' => false,
        'volatile' => true,
    ]];
}
