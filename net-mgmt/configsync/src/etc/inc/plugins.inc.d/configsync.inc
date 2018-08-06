<?php

/*
    Copyright (c) 2018 Verb Networks Pty Ltd <contact@verbnetworks.com>
    Copyright (c) 2018 Nicholas de Jong <me@nicholasdejong.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
    ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

function configsync_enabled()
{
    $model_configsync = new \VerbNetworks\ConfigSync\ConfigSync();
    return $model_configsync->settings->Enabled->__toString() == "1";
}

function configsync_services()
{
    $services = array();

    if (!configsync_enabled()) {
        return $services;
    }

    $services[] = array(
        'description' => gettext('Configuration Sync Daemon'),
        'configd' => array(
            'restart' => array('configsync restart'),
            'start' => array('configsync start'),
            'stop' => array('configsync stop'),
        ),
        'name' => 'configsync',
        'pidfile' => '/var/run/configsync-monitordaemon.pid'
    );

    return $services;
}

function configsync_syslog()
{
    $logfacilities = array();

    $logfacilities['configsync'] = array(
        'facility' => array('configsync', 'configsync-monitordaemon'),
    );

    return $logfacilities;
}
