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
 * Discover collector classes from PHP files in a directory.
 *
 * Each file in $dir should define a class named ucfirst(basename) + "Collector".
 * For example, gateway.php should define GatewayCollector.
 *
 * @param string $dir Path to the collectors directory
 * @return array Map of type => class name, e.g. ['gateway' => 'GatewayCollector']
 */
function load_collectors(string $dir): array
{
    $collectors = [];
    $files = glob($dir . '/*.php');

    if ($files === false) {
        return $collectors;
    }

    foreach ($files as $file) {
        $type = basename($file, '.php');
        $class = ucfirst($type) . 'Collector';

        require_once $file;

        if (!class_exists($class, false)) {
            syslog(LOG_WARNING, sprintf(
                'Metrics exporter: collector file %s does not define expected class %s',
                $file,
                $class
            ));
            continue;
        }

        $collectors[$type] = $class;
    }

    return $collectors;
}
