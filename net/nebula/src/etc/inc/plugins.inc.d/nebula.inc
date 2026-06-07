<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
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

function nebula_enabled()
{
    return (string)(new \OPNsense\Nebula\Nebula())->general->enabled == '1';
}

function nebula_services()
{
    $services = [];

    if (!nebula_enabled()) {
        return $services;
    }

    $services[] = [
        'description' => gettext('Nebula'),
        'configd' => [
            'start' => ['nebula start'],
            'restart' => ['nebula restart'],
            'stop' => ['nebula stop'],
        ],
        'nocheck' => true, /* multi-instance: no single pidfile to check */
        'name' => 'nebula',
    ];

    return $services;
}

function nebula_syslog()
{
    return [
        'nebula' => ['facility' => ['nebula']]
    ];
}

/*
 * Auto-add an inbound "allow UDP to the listen port" rule for each enabled
 * instance, on the interface(s) the user selected (firewall_interfaces; default
 * WAN). Empty selection = no rule. nebula binds dual-stack on "::" (the default),
 * so open both families then; a specific listen address opens only its family.
 */
function nebula_firewall($fw)
{
    if (!nebula_enabled()) {
        return;
    }

    foreach ((new \OPNsense\Nebula\Nebula())->instances->instance->iterateItems() as $uuid => $inst) {
        if ((string)$inst->enabled !== '1') {
            continue;
        }
        $ifaces = array_filter(explode(',', (string)$inst->firewall_interfaces));
        if (empty($ifaces)) {
            continue;
        }
        $port = (int)(string)$inst->listen_port;
        if ($port <= 0) {
            continue; /* port 0 = OS-assigned ephemeral; no fixed port to open */
        }
        $descr = (string)$inst->description;
        if ($descr === '') {
            $descr = $uuid;
        }

        $host = trim((string)$inst->listen_host);
        if ($host === '' || $host === '::') {
            $families = ['inet', 'inet6'];
        } elseif (strpos($host, ':') !== false) {
            $families = ['inet6'];
        } else {
            $families = ['inet'];
        }

        foreach ($ifaces as $if) {
            foreach ($families as $family) {
                $fw->registerFilterRule(
                    500000,
                    [
                        'direction'  => 'in',
                        'interface'  => $if,
                        'ipprotocol' => $family,
                        'protocol'   => 'udp',
                        'from'       => 'any',
                        'to'         => '(self)',
                        'to_port'    => $port,
                        'descr'      => "Nebula {$descr}: allow listen port {$port}",
                        '#ref'       => 'ui/nebula/instances',
                    ]
                );
            }
        }
    }
}

/*
 * Register a virtual 'nebula' interface group with OPNsense (the _interfaces
 * hook, like wireguard_interfaces). This surfaces "Nebula (Group)" in the
 * firewall — it appears in the Interface dropdown when adding a rule (Firewall >
 * Rules), letting you write one rule against every nebula tun at once. (It is a
 * kernel/pf interface group, NOT a user-created Firewall > Groups entry, so it
 * does not appear under Firewall > Groups.) The group is backed by the real
 * `ifconfig <dev> group nebula` membership joined at instance start / prepare.
 */
function nebula_interfaces()
{
    $interfaces = [];
    if (!nebula_enabled()) {
        return $interfaces;
    }
    $interfaces['nebula'] = [
        'descr'    => gettext('Nebula (Group)'),
        'if'       => 'nebula',
        'virtual'  => true,
        'enable'   => true,
        'type'     => 'group',
        'networks' => [],
    ];
    return $interfaces;
}

/*
 * Register each enabled instance's tun device with OPNsense so it is a
 * first-class, idiomatic interface (like wireguard's wgN): it shows up in
 * Interfaces > Assignments labelled "<dev> (Nebula - <descr>)", joins the
 * 'nebula' group, and is marked non-configurable (nebula owns the cert-derived
 * IP) and volatile (the daemon recreates it). Names come from the stable,
 * per-instance tun_name (nebula + md5(uuid) hex), so only real instances appear
 * — stale/test tun devices do not.
 */
function nebula_devices()
{
    $names = [];
    if (nebula_enabled()) {
        foreach ((new \OPNsense\Nebula\Nebula())->instances->instance->iterateItems() as $uuid => $node) {
            if ((string)$node->enabled !== '1') {
                continue;
            }
            $dev = trim((string)$node->tun_name);
            if ($dev === '') {
                continue;
            }
            $descr = (string)$node->description !== '' ? (string)$node->description : $dev;
            $names[$dev] = [
                'name'    => $dev,
                'descr'   => sprintf('%s (Nebula - %s)', $dev, $descr),
                'ifdescr' => $descr,
            ];
        }
    }

    return [[
        'function'     => 'nebula_prepare',
        'configurable' => false, /* nebula assigns the overlay IP from the cert */
        'pattern'      => '^nebula',
        'type'         => 'nebula',
        'volatile'     => true,  /* the daemon creates/destroys the tun */
        'names'        => $names,
    ]];
}

/*
 * Device-creation callback OPNsense invokes during interface bring-up, before
 * configuring an assigned interface — this is what fixes boot ordering. Nebula
 * owns tun creation (it opens /dev/tun and renames to tun.dev; FreeBSD refuses to
 * rename onto an existing name), so we cannot pre-create the device the way
 * wireguard_prepare() does. Instead start the matching instance's daemon (which
 * creates the tun) and wait briefly for it, then join the 'nebula' group. Run
 * setup.php directly rather than via configd to avoid reentrancy from inside
 * interfaces_configure().
 */
function nebula_prepare($device)
{
    if (!nebula_enabled()) {
        return null;
    }
    foreach ((new \OPNsense\Nebula\Nebula())->instances->instance->iterateItems() as $uuid => $node) {
        if ((string)$node->enabled !== '1' || trim((string)$node->tun_name) !== $device) {
            continue;
        }
        if (!does_interface_exist($device)) {
            mwexecf('/usr/local/opnsense/scripts/OPNsense/Nebula/setup.php start %s', [$uuid]);
            for ($i = 0; $i < 15 && !does_interface_exist($device); $i++) {
                usleep(200 * 1000); /* up to ~3s; nebula creates the tun early */
            }
        }
        if (does_interface_exist($device)) {
            mwexecf('/sbin/ifconfig %s group nebula', [$device]);
            return $device;
        }
        return null;
    }
    return null;
}

function nebula_configure()
{
    return [
        'vpn' => ['nebula_configure_do:2'],
    ];
}

function nebula_configure_do($verbose = false)
{
    if (!nebula_enabled()) {
        return;
    }

    service_log('Configuring Nebula overlay...', $verbose);

    configd_run('nebula start');

    service_log("done.\n", $verbose);
}
