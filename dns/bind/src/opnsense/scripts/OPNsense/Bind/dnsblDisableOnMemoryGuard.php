#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Bryan Wiegand <inbox@kw-ventures.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/*
 * Persist the safe DNSBL-off state after named's startup memory guard trips.
 * The selected lists remain configured, so an administrator can re-enable
 * DNSBL after reducing the selection or adding memory.
 */

require_once("util.inc");
require_once("config.inc");

use OPNsense\Core\Config;

$expectedSelection = $argv[1] ?? '';
if ($expectedSelection === '') {
    exit(1);
}

$configuration = Config::getInstance()->lock();
try {
    $config = $configuration->toArray(listtags());
    if (!isset($config['OPNsense']['bind']['dnsbl']) ||
        !is_array($config['OPNsense']['bind']['dnsbl'])) {
        exit(1);
    }

    $dnsbl = &$config['OPNsense']['bind']['dnsbl'];
    if (($dnsbl['enabled'] ?? '0') !== '1') {
        exit(0);
    }
    if (!hash_equals($expectedSelection, (string)($dnsbl['type'] ?? ''))) {
        exit(2);
    }

    $dnsbl['enabled'] = '0';
    if (write_config('Disabled BIND DNSBL after the DNSBL startup memory guard reached the free-memory floor.') === false) {
        exit(1);
    }
} finally {
    $configuration->unlock();
}

exit(0);
