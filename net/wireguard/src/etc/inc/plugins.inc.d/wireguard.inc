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
    $services = array();

    if (!wireguard_enabled()) {
        return $services;
    }

    $services[] = array(
        'description' => gettext('WireGuard VPN'),
        'configd' => array(
            'restart' => array('wireguard restart'),
            'start' => array('wireguard start'),
            'stop' => array('wireguard stop'),
        ),
        'name' => 'wireguard-go'
    );

    return $services;
}

function wireguard_interfaces()
{
    $interfaces = array();
    if (!wireguard_enabled()) {
        return $interfaces;
    }
    $oic = array('enable' => true);
    $oic['if'] = 'wireguard';
    $oic['descr'] = gettext('WireGuard (Group)');
    $oic['type'] = 'group';
    $oic['virtual'] = true;
    $oic['networks'] = array();
    $interfaces['wireguard'] = $oic;
    return $interfaces;
}

function wireguard_xmlrpc_sync()
{
    $result = array();
    $result['id'] = 'wireguard';
    $result['section'] = 'OPNsense.wireguard';
    $result['description'] = gettext('WireGuard');
    $result['services'] = ['wireguard-go'];
    return array($result);
}

function wireguard_devices()
{
    return [['pattern' => '^wg', 'volatile' => true]];
}
