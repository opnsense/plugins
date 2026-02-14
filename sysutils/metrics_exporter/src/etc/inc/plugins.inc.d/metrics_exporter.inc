<?php

/*
 * Copyright (C) 2026 Brendan Bank
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

function metrics_exporter_services()
{
    $services = array();
    $mdl = new \OPNsense\MetricsExporter\MetricsExporter();

    if ($mdl->enabled->__toString() == "0") {
        return $services;
    }

    $services[] = array(
        'description' => gettext('Metrics Exporter'),
        'configd' => array(
            'restart' => array('metrics_exporter restart'),
            'start' => array('metrics_exporter start'),
            'stop' => array('metrics_exporter stop'),
        ),
        'name' => 'metrics_exporter',
        'pidfile' => '/var/run/metrics_exporter.pid',
    );

    return $services;
}

function metrics_exporter_configure()
{
    return [
        'bootup' => ['metrics_exporter_start:0'],
    ];
}

function metrics_exporter_syslog()
{
    return [
        'metrics_exporter' => ['facility' => ['metrics_exporter']],
    ];
}

function metrics_exporter_start()
{
    $mdl = new \OPNsense\MetricsExporter\MetricsExporter();

    if ($mdl->enabled->__toString() == "1") {
        configd_run('metrics_exporter start');
    }
}
