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

function openconnect_enabled()
{
    $model = new \OPNsense\Openconnect\General();

    return (string)$model->enabled == '1';
}

function openconnect_services()
{
    $services = array();

    if (openconnect_enabled()) {
        $services[] = array(
            'description' => gettext('OpenConnect'),
            'configd' => array(
                'restart' => array('openconnect restart'),
                'start' => array('openconnect start'),
                'stop' => array('openconnect stop'),
            ),
            'name' => 'openconnect',
            'pidfile' => '/var/run/openconnect.pid'
        );
    }

    return $services;
}

function openconnect_interfaces()
{
    $interfaces = array();

    if (!openconnect_enabled()) {
        return $interfaces;
    }

    $oic = array('enable' => true);
    $oic['if'] = 'ocvpn';
    $oic['descr'] = 'OpenConnect';
    $oic['type'] = 'group';
    $oic['virtual'] = true;
    $oic['networks'] = array();
    $interfaces['ocvpn'] = $oic;

    return $interfaces;
}

function openconnect_xmlrpc_sync()
{
    $result = array();
    $result['id'] = 'openconnectvpn';
    $result['section'] = 'OPNsense.openconnect';
    $result['description'] = gettext('OpenConnect');
    $result['services'] = ['openconnect'];
    return array($result);
}

function openconnect_devices()
{
    return [['pattern' => '^ocvpn', 'volatile' => true]];
}
