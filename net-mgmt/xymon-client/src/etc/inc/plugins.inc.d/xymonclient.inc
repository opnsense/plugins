<?php

/*
    Copyright (c) 2024 Kumy

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
    SOFTWARE.
*/

function xymonclient_enabled()
{
    return (string)(new Kumy\XymonClient\Settings())->enabled == '1';
}

function xymonclient_services()
{
    global $config;

    $services = [];

    $fqdn = sprintf('%s.%s',
        $config['system']['hostname'],
        $config['system']['domain'],
    );

    if (xymonclient_enabled()) {
        $services[] = [
            'description' => gettext('Xymon client'),
            'configd' => [
                'restart' => ['xymonclient restart'],
                'start' => ['xymonclient start'],
                'stop' => ['xymonclient stop'],
            ],
            'name' => 'xymonclient',
            'pidfile' => "/var/run/xymon/xymon_client.pid"
        ];
    }

    return $services;
}

function xymonclient_xmlrpc_sync()
{
    $result = [];

    $result[] = [
        'description' => gettext('Xymon client'),
        'section' => 'kumy.xymon.client',
        'id' => 'xymonclient',
        'services' => ['xymonclient'],
    ];

    return $result;
}
