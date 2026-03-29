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
 * Wrapper script for "netbird up" that automatically reloads the packet
 * filter after the wt0 interface is created.
 *
 * When NetBird brings its tunnel up, it creates a tun device and renames
 * it to wt0.  The packet filter does not recognise the new interface until
 * a filter reload is performed, causing all traffic on wt0 to be dropped.
 *
 * This script:
 *   1. Checks whether NetBird is already connected (skip reload if so).
 *   2. Runs `/usr/local/bin/netbird up` with any arguments passed through
 *      by configd (e.g. -m <url> -k <key>).
 *   3. If NetBird was not already connected, polls for the wt0 interface
 *      and triggers `configctl filter reload` once it appears.
 *
 * All configd actions that invoke "netbird up" should point here so that
 * filter reloads happen regardless of the caller (API, CARP, CLI).
 */

require_once("config.inc");
require_once("util.inc");

$wt_iface = 'wt0';
$poll_interval = 1;   // seconds between interface checks
$poll_timeout  = 15;  // maximum seconds to wait for interface

// --- Determine current connection state before bringing up -----------------
$was_connected = false;
$status_json = shell_exec('/usr/local/bin/netbird status --json 2>/dev/null');
if ($status_json !== null) {
    $status = json_decode($status_json, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $was_connected = ($status['management']['connected'] ?? false) === true;
    }
}

// Check whether wt0 already exists before running "netbird up".  If NetBird
// is not connected and wt0 is present, it's orphaned from a previous run —
// destroy it now so we have a clean slate.  NetBird will create a new wt0 
// when it starts, and we won't have to worry about the old one.
// Only destroy if NetBird is actually disconnected; if it's connected, the
// interface is legitimately in use.
exec("/sbin/ifconfig " . escapeshellarg($wt_iface) . " 2>/dev/null", $pre_out, $pre_rc);
if ($pre_rc === 0 && !$was_connected) {
    log_msg("NetBird: Found orphaned {$wt_iface} interface before 'netbird up', destroying");
    mwexecf("/sbin/ifconfig " . escapeshellarg($wt_iface) . " destroy");
}

// --- Build and execute the real "netbird up" command ------------------------
// $argv[0] is this script; everything after is passed through by configd
// (e.g. "-m https://mgmt.example.com -k SETUP-KEY").
$extra_args = array_slice($argv, 1);
$cmd = '/usr/local/bin/netbird up';
if (!empty($extra_args)) {
    $cmd .= ' ' . implode(' ', array_map('escapeshellarg', $extra_args));
}

// Run netbird up in the background.  It can block indefinitely (e.g.
// waiting for authentication or an unreachable management server), so
// we fork it and proceed to poll for the wt0 interface.
mwexecfm($cmd);

// --- Filter reload logic ---------------------------------------------------
// Only reload when transitioning from disconnected → connected.  If NetBird
// was already up, the interface already exists and the filter already knows
// about it.
if ($was_connected) {
    exit(0);
}

// Poll for the wt0 interface to appear.  NetBird creates a tun device and
// renames it to wt0, which takes a few seconds.  Any orphaned wt0 was
// already destroyed above, so this will only match the freshly created one.
$found = false;
for ($i = 0; $i < $poll_timeout; $i += $poll_interval) {
    $iface_out = [];
    exec("/sbin/ifconfig " . escapeshellarg($wt_iface) . " 2>/dev/null", $iface_out, $iface_rc);
    if ($iface_rc === 0) {
        $found = true;
        break;
    }
    sleep($poll_interval);
}

if ($found) {
    log_msg("NetBird: {$wt_iface} interface detected after 'netbird up', reloading packet filter");
    mwexecfm('/usr/local/sbin/configctl filter reload');
} else {
    log_msg("NetBird: Timeout ({$poll_timeout}s) waiting for {$wt_iface} interface after 'netbird up'. Packet filter reload skipped.");
}

exit(0);
