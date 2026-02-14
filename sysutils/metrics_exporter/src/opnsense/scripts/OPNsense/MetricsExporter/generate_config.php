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
 * Generate metrics exporter config file from OPNsense model/config.
 * Discovers collectors and merges their defaults with user overrides.
 * Runs as root via configd before starting the unprivileged daemon.
 */

require_once 'config.inc';
require_once __DIR__ . '/lib/collector_loader.php';

define('COLLECTORS_DIR', __DIR__ . '/collectors');

$mdl = new \OPNsense\MetricsExporter\MetricsExporter();

$interval = (int)$mdl->interval->__toString();
if ($interval < 5 || $interval > 300) {
    $interval = 15;
}

$outputdir = $mdl->outputdir->__toString();
if (empty($outputdir) || strpos($outputdir, '..') !== false) {
    $outputdir = '/var/tmp/node_exporter/';
}
if (substr($outputdir, -1) !== '/') {
    $outputdir .= '/';
}

// Discover collectors and build enabled map
$all_collectors = load_collectors(COLLECTORS_DIR);

$overrides_json = $mdl->collectors->__toString();
$overrides = [];
if (!empty($overrides_json)) {
    $decoded = json_decode($overrides_json, true);
    if (is_array($decoded)) {
        $overrides = $decoded;
    }
}

$collectors_config = [];
foreach ($all_collectors as $type => $class) {
    if (isset($overrides[$type])) {
        $collectors_config[$type] = (bool)$overrides[$type];
    } else {
        $collectors_config[$type] = $class::defaultEnabled();
    }
}

$config = [
    'interval' => $interval,
    'outputdir' => $outputdir,
    'collectors' => $collectors_config,
];

// Write config file (readable by unprivileged daemon)
$config_path = '/usr/local/etc/metrics_exporter.conf';
file_put_contents($config_path, json_encode($config, JSON_PRETTY_PRINT) . "\n");
chmod($config_path, 0644);

// Ensure output directory exists and is writable by the daemon (runs as nobody)
if (!is_dir($outputdir)) {
    mkdir($outputdir, 01777, true);
} else {
    $perms = fileperms($outputdir);
    if (($perms & 0002) === 0) {
        chmod($outputdir, $perms | 0003);
    }
}

// Fix ownership of any existing .prom files so the unprivileged daemon can
// overwrite them (needed when upgrading from a version that ran as root).
foreach (glob($outputdir . '*.prom') as $prom) {
    chown($prom, 'nobody');
    chgrp($prom, 'nobody');
}
