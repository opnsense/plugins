<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

function tinc_enabled()
{
    $mdl = new \OPNsense\Tinc\Tinc();

    foreach ($mdl->networks->network->iterateItems() as $network) {
        if ($network->enabled == '1') {
            return true;
        }
    }

    return false;
}

function tinc_syslog()
{
    $logfacilities = [];

    $logfacilities['tinc'] = [
        'facility' => ['tincd'],
    ];

    $mdl = new \OPNsense\Tinc\Tinc();

    foreach ($mdl->networks->network->iterateItems() as $network) {
        if ($network->enabled == '1') {
            $logfacilities['tinc']['facility'][] = 'tinc.' . $network->name;
        }
    }

    return $logfacilities;
}

function tinc_services()
{
    $services = array();

    if (!tinc_enabled()) {
        return $services;
    }

    $services[] = array(
        'description' => gettext('Tinc VPN Server'),
        'configd' => array(
            'restart' => array('tinc restart'),
            'start' => array('tinc start'),
            'stop' => array('tinc stop'),
        ),
        'name' => 'tincd',
    );

    return $services;
}

function tinc_interfaces()
{
    $interfaces = array();

    if (!tinc_enabled()) {
        return $interfaces;
    }

    $oic = array('enable' => true);
    $oic['if'] = 'tinc';
    $oic['descr'] = 'TincVPN';
    $oic['type'] = 'none';
    $oic['virtual'] = true;
    $oic['networks'] = array();
    $interfaces['tinc'] = $oic;

    return $interfaces;
}

function tinc_xmlrpc_sync()
{
    $result = array();
    $result['id'] = 'tincvpn';
    $result['section'] = 'OPNsense.Tinc';
    $result['description'] = gettext('Tinc VPN');
    $result['services'] = ['tincd'];
    return array($result);
}

function tinc_devices()
{
    return [['pattern' => '^tinc', 'volatile' => true]];
}
