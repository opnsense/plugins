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
 * Wrapper script for "netbird restart" that automatically reloads the
 * packet filter after the wt0 interface is recreated.
 *
 * When NetBird restarts, it destroys the existing wt0 interface, creates
 * a fresh tun device, and renames it to wt0.  The packet filter does not
 * recognise the new interface until a filter reload is performed, causing
 * all traffic on wt0 to be dropped.  This is particularly important after
 * a WAN failover with a default gateway switch, where NetBird is restarted
 * to bind to the new path.
 *
 * This script:
 *   1. Runs `/usr/local/etc/rc.d/netbird restart` (the real rc.d restart).
 *   2. Polls for the wt0 interface to appear.
 *   3. Triggers `configctl filter reload` once it appears.
 */

require_once("config.inc");
require_once("util.inc");

$wt_iface = 'wt0';
$poll_interval = 1;   // seconds between interface checks
$poll_timeout  = 15;  // maximum seconds to wait for interface

// --- Restart the NetBird service -------------------------------------------
log_msg("NetBird: Restarting service");
mwexec('/usr/local/etc/rc.d/netbird restart');

// --- Check if NetBird is actually connected after restart ------------------
// On a CARP BACKUP node the start_postcmd runs "netbird down", so the tunnel
// is intentionally not established and wt0 will never appear.  Skip the
// interface poll entirely in that case to avoid a 15-second timeout.
sleep(2);

$is_connected = false;
$status_json = shell_exec('/usr/local/bin/netbird status --json 2>/dev/null');
if ($status_json !== null) {
    $status = json_decode($status_json, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $is_connected = ($status['management']['connected'] ?? false) === true;
    }
}

if (!$is_connected) {
    log_msg("NetBird: Service not connected after restart (CARP BACKUP or not authenticated), skipping filter reload");
    exit(0);
}

// --- Poll for the wt0 interface to reappear --------------------------------
// The restart destroys the old wt0 and creates a new one.  We need to wait
// for the new interface before reloading the filter.
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
    log_msg("NetBird: {$wt_iface} interface detected after restart, reloading packet filter");
    mwexecfm('/usr/local/sbin/configctl filter reload');
} else {
    log_msg("NetBird: Timeout ({$poll_timeout}s) waiting for {$wt_iface} interface after restart. Packet filter reload skipped.");
}

exit(0);
