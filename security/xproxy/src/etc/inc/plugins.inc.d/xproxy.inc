<?php

/*
 * Copyright (C) 2025 OPNsense Community
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

function xproxy_configure()
{
    return array(
        'bootup' => array('xproxy_configure_do')
    );
}

function xproxy_sync_gateway_config()
{
    $script = '/usr/local/opnsense/scripts/xproxy/sync_gateway.php';
    if (is_file($script)) {
        exec('/usr/local/bin/php ' . escapeshellarg($script) . ' 2>/dev/null');
    }
}

function xproxy_configure_do($verbose = false)
{
    xproxy_sync_gateway_config();

    $mdl = new \OPNsense\Xproxy\Xproxy();
    if ((string)$mdl->general->enabled == '1') {
        if ($verbose) {
            echo 'Starting Xproxy...';
        }
        configd_run('xproxy setup');
        configd_run('template reload OPNsense/Xproxy');
        configd_run('xproxy start');
        configd_run('filter reload');
    } else {
        configd_run('xproxy stop');
        configd_run('filter reload');
    }
}

function xproxy_policy_route_enabled()
{
    if (!file_exists('/var/run/xproxy_service.active')) {
        return false;
    }

    $mdl = new \OPNsense\Xproxy\Xproxy();
    if ((string)$mdl->general->enabled != '1') {
        return false;
    }
    if ((string)$mdl->general->policy_route_lan === '0') {
        return false;
    }
    $active = (string)$mdl->general->active_server;
    if (empty($active)) {
        return false;
    }

    foreach ($mdl->servers->server->iterateItems() as $uuid => $srv) {
        if ($uuid === $active) {
            return true;
        }
    }
    return false;
}

function xproxy_firewall(\OPNsense\Firewall\Plugin $fw)
{
    global $config;

    if (!xproxy_policy_route_enabled()) {
        return;
    }

    $defaults = array(
        'ipprotocol' => 'inet',
        'type' => 'pass',
        'quick' => true,
        'statetype' => 'keep',
        'disablereplyto' => true,
    );

    if (empty($config['interfaces']) || !is_array($config['interfaces'])) {
        return;
    }

    $mdl = new \OPNsense\Xproxy\Xproxy();
    $bypassRaw = (string)$mdl->general->bypass_ips;
    $bypassCidrs = array();
    if (!empty($bypassRaw)) {
        foreach (explode(',', $bypassRaw) as $cidr) {
            $cidr = trim($cidr);
            if (!empty($cidr)) {
                $bypassCidrs[] = $cidr;
            }
        }
    }

    $selectedIfs = array();
    $routeIfRaw = (string)$mdl->general->route_interfaces;
    if (!empty($routeIfRaw)) {
        foreach (explode(',', $routeIfRaw) as $entry) {
            $entry = trim($entry);
            if (!empty($entry)) {
                $selectedIfs[] = $entry;
            }
        }
    }

    foreach ($config['interfaces'] as $ifent => $iface) {
        if (!is_array($iface) || empty($iface['enable'])) {
            continue;
        }
        if (!empty($iface['virtual'])) {
            continue;
        }
        if ($ifent === 'wan' || $ifent === 'xproxytun') {
            continue;
        }
        if (!empty($selectedIfs) && !in_array($ifent, $selectedIfs)) {
            continue;
        }

        // Allow access to the firewall itself (anti-lockout)
        $fw->registerFilterRule(
            150,
            array(
                'interface' => $ifent,
                'descr' => 'Xproxy: allow access to this firewall (' . $ifent . ')',
                'from' => $ifent,
                'to' => '(self)',
                'direction' => 'in',
            ),
            $defaults
        );

        // Bypass: let traffic to local/private networks pass directly
        foreach ($bypassCidrs as $cidr) {
            $fw->registerFilterRule(
                175,
                array(
                    'interface' => $ifent,
                    'descr' => 'Xproxy: bypass tunnel for ' . $cidr . ' (' . $ifent . ')',
                    'from' => $ifent,
                    'to' => $cidr,
                    'direction' => 'in',
                ),
                $defaults
            );
        }

        // Bypass traffic destined for the same interface network
        $fw->registerFilterRule(
            176,
            array(
                'interface' => $ifent,
                'descr' => 'Xproxy: bypass tunnel for same-subnet (' . $ifent . ')',
                'from' => $ifent,
                'to' => $ifent,
                'direction' => 'in',
            ),
            $defaults
        );

        // Route all other IPv4 traffic through the tunnel
        $fw->registerFilterRule(
            200,
            array(
                'interface' => $ifent,
                'descr' => 'Xproxy: route IPv4 via tunnel (' . $ifent . ')',
                'from' => $ifent,
                'to' => 'any',
                'direction' => 'in',
                'gateway' => 'XPROXY_TUN',
            ),
            $defaults
        );
    }
}

function xproxy_devices()
{
    $mdl = new \OPNsense\Xproxy\Xproxy();
    if ((string)$mdl->general->enabled != '1') {
        return array();
    }
    $dev = (string)$mdl->general->tun_device;
    if ($dev === '') {
        $dev = 'tun9';
    }
    return array(array('pattern' => '^' . preg_quote($dev, '/') . '$', 'volatile' => true));
}

function xproxy_services()
{
    $services = array();
    $mdl = new \OPNsense\Xproxy\Xproxy();
    if ((string)$mdl->general->enabled == '1') {
        $services[] = array(
            'description' => gettext('Xproxy'),
            'configd' => array(
                'restart' => array('xproxy restart'),
                'start' => array('xproxy start'),
                'stop' => array('xproxy stop'),
            ),
            'name' => 'xproxy',
            'pidfile' => '/var/run/xproxy_xray.pid',
        );
    }
    return $services;
}
