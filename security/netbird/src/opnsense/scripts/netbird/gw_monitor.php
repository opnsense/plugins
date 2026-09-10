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

/*
 * This script checks whether the default gateway has actually changed
 * since the last invocation.  If so, it restarts NetBird so it can
 * re-establish connections through the new default route.
 *
 * A cached copy of the previous default gateway is stored in /var/run
 * (cleared at boot; the start syshook re-seeds it).  Only when the
 * observed default gateway differs from the cached value does a restart
 * occur, avoiding unnecessary restarts for non-default gateway alarms.
 *
 * The dpinger alarm that triggers this script fires when a gateway
 * changes *state*; the default route replacement happens asynchronously
 * afterwards and emits no further alarm.  A single immediate sample could
 * therefore still see the old (or no) default route and miss the switch
 * for good, so the routing table is sampled for a settle window instead,
 * restarting once a new default gateway is observed twice in a row.  A
 * PID token collapses alarm bursts: any newer instance takes over and
 * older ones exit at the next check.
 */

require_once("config.inc");
require_once("util.inc");
require_once("plugins.inc.d/netbird.inc");

if (!netbird_enabled()) {
    log_msg("NetBird monitor: NetBird is disabled, not restarting");
    exit(0);
}

$token_file = '/var/run/netbird_gw_monitor.token';
$cache_file = '/var/run/netbird_default_gw.cache';

function netbird_default_gw()
{
    $gw_output = shell_exec('/sbin/route -n get default 2>/dev/null');
    if ($gw_output !== null && preg_match('/gateway:\s+(\S+)/', $gw_output, $matches)) {
        return $matches[1];
    }
    return '';
}

// Claim this event slot; see carp_event.php for why file contents (PID)
// are used instead of filemtime and why the token is never deleted.
$my_token = (string)getmypid();
file_put_contents($token_file, $my_token, LOCK_EX);

sleep(2);

if (@file_get_contents($token_file) !== $my_token) {
    exit(0);
}

$cached_gw = @file_get_contents($cache_file);
$cached_gw = ($cached_gw !== false) ? trim($cached_gw) : '';

// Sample the routing table until a new default gateway settles or the
// window closes.  "No default route" keeps the window open — the route is
// usually mid-replacement at that point.
$new_gw = '';
$last_gw = null;
$deadline = time() + 30;
while (time() < $deadline) {
    if (@file_get_contents($token_file) !== $my_token) {
        // a newer alarm superseded this instance
        exit(0);
    }

    $current_gw = netbird_default_gw();
    if ($current_gw !== '' && $current_gw !== $cached_gw) {
        if ($current_gw === $last_gw) {
            // stable for two consecutive samples
            $new_gw = $current_gw;
            break;
        }
        $last_gw = $current_gw;
    } else {
        $last_gw = null;
    }

    sleep(2);
}

if ($new_gw === '') {
    log_msg("NetBird monitor: No default gateway change detected, not restarting NetBird");
    exit(0);
}

file_put_contents($cache_file, $new_gw, LOCK_EX);

// If there was no previous cache (first run / fresh boot), don't restart —
// just seed the cache so future changes are detected.
if ($cached_gw === '') {
    log_msg("NetBird monitor: Seeding default gateway cache with {$new_gw}");
    exit(0);
}

// On a CARP BACKUP node the tunnel is intentionally down; remember the new
// gateway but leave the (restart + guard) churn to the MASTER.
if (netbird_carp_enabled() && !netbird_carp_check_master()) {
    log_msg("NetBird monitor: Default gateway changed to {$new_gw} but node is CARP BACKUP, not restarting NetBird");
    exit(0);
}

log_msg("NetBird monitor: Default gateway changed from {$cached_gw} to {$new_gw}, restarting NetBird");
mwexecfm('/usr/local/sbin/configctl netbird restart');
