<?php

/*
 * Copyright (C) 2020 Michael Muenz <m.muenz@gmail.com>
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

function tayga_enabled()
{
    $model = new \OPNsense\Tayga\General();

    return (string)$model->enabled == '1';
}

function tayga_services()
{
    $services = array();

    if (tayga_enabled()) {
        $services[] = array(
            'description' => gettext('Tayga'),
            'configd' => array(
                'restart' => array('tayga restart'),
                'start' => array('tayga start'),
                'stop' => array('tayga stop'),
            ),
            'name' => 'tayga',
            'pidfile' => '/var/run/tayga.pid'
        );
    }

    return $services;
}

function tayga_xmlrpc_sync()
{
    $result = array();
    $result['id'] = 'taygavpn';
    $result['section'] = 'OPNsense.tayga';
    $result['description'] = gettext('Tayga');
    $result['services'] = ['tayga'];
    return array($result);
}

function tayga_interfaces()
{
    $interfaces = array();
    if (!tayga_enabled()) {
        return $interfaces;
    }
    $oic = array('enable' => true);
    $oic['if'] = 'tayga';
    $oic['descr'] = 'Tayga';
    $oic['type'] = 'group';
    $oic['virtual'] = true;
    $oic['networks'] = array();
    $interfaces['tayga'] = $oic;
    return $interfaces;
}
