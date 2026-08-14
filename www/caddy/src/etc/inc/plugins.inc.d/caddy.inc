<?php

/*
 * Copyright (C) 2023-2025 Cedrik Pischem
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

function caddy_services()
{
    global $config;

    $services = array();

    if (
        isset($config['OPNsense']['caddy']['general']['enabled']) &&
        $config['OPNsense']['caddy']['general']['enabled'] == 1
    ) {
        $services[] = array(
            'description' => gettext('Caddy'),
            'configd' => array(
                'restart' => array('caddy restart'),
                'start' => array('caddy start'),
                'stop' => array('caddy stop'),
            ),
            'name' => 'caddy',
            'pidfile' => '/var/run/caddy/caddy.pid'
        );
    }

    return $services;
}

function caddy_configure()
{
    return [
        'caddy_sync' => ['caddy_configure_do'],
    ];
}

function caddy_configure_do($verbose = false)
{
    (new \OPNsense\Core\Backend())->configdpRun('template reload', ['OPNsense/Caddy/Config']);
    mwexecf('/usr/local/bin/caddy fmt --overwrite /usr/local/etc/caddy/Caddyfile');
    mwexecf('/usr/local/opnsense/scripts/OPNsense/Caddy/caddy_certs.php');
}

function caddy_xmlrpc_sync()
{
    $result = array();

    $result[] = array(
        'description' => gettext('Caddy'),
        'section' => 'OPNsense.caddy',
        'id' => 'caddy',
        'services' => ["caddy"],
    );

    return $result;
}

function caddy_syslog()
{
    return ['caddy' => ['facility' => ['caddy']]];
}
