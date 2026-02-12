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

/**
 * List available collectors with their enabled state.
 * Called by configd [list-collectors] action, used by the Settings page API.
 */

require_once 'config.inc';
require_once __DIR__ . '/lib/collector_loader.php';

define('COLLECTORS_DIR', __DIR__ . '/collectors');

$mdl = new \OPNsense\MetricsExporter\MetricsExporter();

$overrides_json = $mdl->collectors->__toString();
$overrides = [];
if (!empty($overrides_json)) {
    $decoded = json_decode($overrides_json, true);
    if (is_array($decoded)) {
        $overrides = $decoded;
    }
}

$all_collectors = load_collectors(COLLECTORS_DIR);
$result = [];

foreach ($all_collectors as $type => $class) {
    $default = $class::defaultEnabled();
    $enabled = isset($overrides[$type]) ? (bool)$overrides[$type] : $default;

    $result[] = [
        'type' => $type,
        'name' => $class::name(),
        'enabled' => $enabled,
        'default' => $default,
    ];
}

echo json_encode($result) . PHP_EOL;
