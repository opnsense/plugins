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
 * A cached copy of the previous default gateway is stored in a temp file.
 * Only when the current default gateway differs from the cached value
 * does a restart occur, avoiding unnecessary restarts for non-default
 * gateway alarms.
 */

require_once("config.inc");
require_once("util.inc");
require_once("plugins.inc.d/netbird.inc");

if (!netbird_enabled()) {
    log_msg("NetBird monitor: NetBird is disabled, not restarting");
    exit(0);
}

$cache_file = '/tmp/netbird_default_gw.cache';

// Get the current default gateway from the routing table
$gw_output = shell_exec('/sbin/route -n get default 2>/dev/null');
$current_gw = '';
if ($gw_output !== null && preg_match('/gateway:\s+(\S+)/', $gw_output, $matches)) {
    $current_gw = $matches[1];
}

if ($current_gw === '') {
    // No default gateway — nothing to do
    log_msg("NetBird monitor: No default gateway detected, not restarting NetBird");
    exit(0);
}

// Compare to the cached gateway
$cached_gw = @file_get_contents($cache_file);
$cached_gw = ($cached_gw !== false) ? trim($cached_gw) : '';

if ($current_gw === $cached_gw) {
    // Default gateway has not changed — no action needed
    log_msg("NetBird monitor: Default gateway has not changed ({$current_gw}), not restarting NetBird");
    exit(0);
}

// Update the cache with the new gateway
file_put_contents($cache_file, $current_gw, LOCK_EX);

// If there was no previous cache (first run / fresh boot), don't restart —
// just seed the cache so future changes are detected.
if ($cached_gw === '') {
    log_msg("NetBird monitor: Seeding default gateway cache with {$current_gw}");
    exit(0);
}

log_msg("NetBird monitor: Default gateway changed from {$cached_gw} to {$current_gw}, restarting NetBird");
mwexecfm('/usr/local/sbin/configctl netbird restart');
