<?php

/*
    Copyright (C) 2016 EURO-LOG AG
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

function ftpproxy_enabled()
{
    $model = new \OPNsense\FtpProxy\FtpProxy();

    foreach ($model->ftpproxy->iterateItems() as $server) {
        if ($server->enabled == '1') {
            return true;
        }
    }

    return false;
}

function ftpproxy_firewall($fw)
{
    if (!ftpproxy_enabled()) {
        return;
    }

    $fw->registerAnchor('ftp-proxy/*', 'nat');
    $fw->registerAnchor('ftp-proxy/*', 'rdr');
    $fw->registerAnchor('ftp-proxy/*', 'fw');
}

function ftpproxy_services()
{
    $services = array();

    if (!ftpproxy_enabled()) {
        return $services;
    }

    $services[] = array(
        'description' => gettext('FTP Proxy Server'),
        'configd' => array(
            'restart' => array('ftpproxy restart'),
            'start' => array('ftpproxy start'),
            'stop' => array('ftpproxy stop'),
        ),
        'name' => 'ftp-proxy',
    );

    return $services;
}
