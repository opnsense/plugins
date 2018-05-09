<?php

/*
 * Copyright (C) 2017-2018 Michael Muenz
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

function nut_enabled()
{
    $model = new \OPNsense\Nut\Nut();

    return (string)$model->general->enable == '1';
}

function nut_netclient()
{
    $model = new \OPNsense\Nut\Nut();

    return (string)$model->general->mode == 'netclient';
}

function nut_services()
{
    $services = array();

    if (!nut_enabled()) {
        return $services;
    }

    $services[] = array(
        'description' => gettext('Network UPS Monitor'),
        'configd' => array(
            'restart' => array('nut restart'),
            'start' => array('nut start'),
            'stop' => array('nut stop'),
        ),
        'pidfile' => '/var/db/nut/upsmon.pid',
        'name' => 'nut_upsmon',
    );

    if (nut_netclient()) {
        return $services;
    }

    $services[] = array(
        'description' => gettext('Network UPS Tools'),
        'configd' => array(
            'restart' => array('nut restart'),
            'start' => array('nut start'),
            'stop' => array('nut stop'),
        ),
        'pidfile' => '/var/db/nut/upsd.pid',
        'name' => 'nut_daemon',
    );

    return $services;
}
