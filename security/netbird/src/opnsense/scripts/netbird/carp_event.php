#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Myah Mitchell, Innovative Networks, Inc. d.b.a INDIGEX
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

require_once("config.inc");
require_once("util.inc");

$subsystem = !empty($argv[1]) ? $argv[1] : 'unknown';
$lockfile = '/var/run/netbird.CARP_MASTER';

// Bring up NetBird's WireGuard interface
log_msg("NetBird CARP: MASTER event from '{$subsystem}', starting NetBird's WireGuard interface");
mwexecfm('/usr/local/bin/netbird up > /dev/null');

// Wait up to 10 seconds for the wt0 device to appear, then reload the
// firewall filter so rules referencing the interface take effect.
$found_wt0 = false;
for ($i = 0; $i < 10; $i++) {
    if (file_exists('/dev/wt0')) {
        $found_wt0 = true;
        break;
    }
    sleep(1);
}

if ($found_wt0) {
    log_msg("NetBird CARP: Found wt0 interface after MASTER event from '{$subsystem}', reloading filter");
    mwexecfm('/usr/local/sbin/configctl filter reload > /dev/null');
} else {
    log_msg("NetBird CARP: Timeout waiting for wt0 interface after MASTER event from '{$subsystem}'. Filter reload skipped.");
}

// Remove the lock file after 10 seconds of inactivity so future MASTER
// events can trigger actions again.  Exits early if a BACKUP event has
// already removed it.
if (file_exists($lockfile)) {
    clearstatcache(true, $lockfile);
    $last_mtime = filemtime($lockfile);
    while (file_exists($lockfile)) {
        sleep(2);
        clearstatcache(true, $lockfile);
        if (!file_exists($lockfile)) {
            // Lock file was already removed (e.g. by a BACKUP event)
            break;
        }
        $current_mtime = filemtime($lockfile);
        if ($current_mtime !== $last_mtime) {
            // Timestamp was updated, reset the timer
            $last_mtime = $current_mtime;
            continue;
        }
        if ((time() - $last_mtime) >= 10) {
            @unlink($lockfile);
            log_msg("NetBird CARP: Lock file removed after 10 seconds of inactivity");
            break;
        }
    }
}
