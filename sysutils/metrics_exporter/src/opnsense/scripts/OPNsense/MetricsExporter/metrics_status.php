#!/usr/local/bin/php
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

require_once __DIR__ . '/lib/collector_loader.php';

define('CONFIG_PATH', '/usr/local/etc/metrics_exporter.conf');
define('COLLECTORS_DIR', __DIR__ . '/collectors');

$config_collectors = [];
$json = @file_get_contents(CONFIG_PATH);
if ($json !== false) {
    $config = json_decode($json, true);
    if ($config !== null) {
        $config_collectors = $config['collectors'] ?? [];
    }
}

$all_collectors = load_collectors(COLLECTORS_DIR);
$collectors_output = [];

foreach ($all_collectors as $type => $class) {
    if (!empty($config_collectors[$type])) {
        try {
            $collectors_output[] = [
                'type' => $type,
                'name' => $class::name(),
                'metrics' => $class::collect(),
            ];
        } catch (\Throwable $e) {
            $collectors_output[] = [
                'type' => $type,
                'name' => $class::name(),
                'metrics' => '',
            ];
        }
    }
}

$result = [
    'collectors' => $collectors_output,
    'node_exporter_installed' => file_exists(
        '/usr/local/etc/inc/plugins.inc.d/node_exporter.inc'
    ),
];

echo json_encode($result) . PHP_EOL;
