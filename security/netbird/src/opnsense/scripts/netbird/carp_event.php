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
 * Gathers events for 2 seconds; if more are triggered in the same slot,
 * execute only the last one.  This moves events until we have at least
 * 2 seconds of "silence" to process them, preventing duplicate actions
 * when multiple CARP interfaces transition simultaneously during failover.
 *
 * The winner does not act on the event type it was invoked with: by the
 * time the burst has settled that type may be stale, and in mixed bursts
 * "last event" is arbitrary.  Instead the current CARP state is probed and
 * NetBird is converged to it, then the result is verified.  The token file
 * is deliberately never deleted — removing it would let an older event
 * erase a newer event's claim, dropping the final state transition.
 *
 * The debounce token only guarantees one winner per 2-second burst; it does
 * not stop the winners of two separate bursts from driving the netbird
 * daemon at the same time.  A "start" can block for 30+ seconds waiting on
 * the tunnel and filter reload, so during sustained flapping a later
 * burst's "stop" can land on the daemon mid-flight and tear the interface
 * down before the filter is ever synced to it.  The action lock below
 * serializes convergence attempts across bursts so that can't happen: a
 * queued winner waits for the in-flight action to fully finish, then
 * re-checks both the token and the live CARP state before acting, so it
 * never acts on stale information.
 */

require_once("config.inc");
require_once("util.inc");
require_once("plugins.inc.d/netbird.inc");

$subsystem = !empty($argv[1]) ? $argv[1] : 'unknown';
$type = !empty($argv[2]) ? $argv[2] : 'unknown';

$debounce_ref = '/var/run/netbird_carp_event.token';

// Write our PID into the reference file to claim this event slot.
// Using file contents (PID) instead of filemtime avoids the 1-second
// granularity limit that allows two events arriving in the same second
// to both pass the debounce check.
$my_token = (string)getmypid();
file_put_contents($debounce_ref, $my_token, LOCK_EX);

sleep(2);

// If another event has overwritten the file with a different PID,
// this event is obsolete
if (@file_get_contents($debounce_ref) !== $my_token) {
    log_msg("NetBird CARP: '{$type}' event from '{$subsystem}' ignored, newer event triggered making this obsolete");
    exit(0);
}

// We won the burst — serialize against any other burst's winner before
// touching the netbird daemon.  flock is released automatically if this
// process exits or is killed, so a crashed handler can't wedge the lock.
$lock_fh = fopen('/var/run/netbird_carp_action.lock', 'c');
if ($lock_fh === false) {
    log_msg('NetBird CARP: failed to open action lock, aborting');
    exit(1);
}
if (!flock($lock_fh, LOCK_EX | LOCK_NB)) {
    log_msg('NetBird CARP: waiting for a previous convergence to finish');
    flock($lock_fh, LOCK_EX);
}

// Time passed while winning the burst and waiting for the lock; a newer
// event may have claimed the token in the meantime, and its handler owns
// the outcome now.
if (@file_get_contents($debounce_ref) !== $my_token) {
    log_msg("NetBird CARP: '{$type}' event from '{$subsystem}' ignored, newer event triggered making this obsolete");
    exit(0);
}

// We are the last event in the burst and hold the action lock — converge
// on the live CARP state
$master = netbird_carp_check_master();
$desired = $master ? 'MASTER' : 'BACKUP';
if ($desired !== $type) {
    log_msg("NetBird CARP: '{$type}' event from '{$subsystem}' superseded by current CARP state '{$desired}'");
}

if ($master) {
    log_msg("NetBird CARP: node is MASTER, starting NetBird's WireGuard interface");
    mwexecfm('/usr/local/sbin/configctl netbird up');

    // Verify the connection comes up; bail out if a newer event claims the
    // token while we wait, its handler owns the outcome from then on.
    for ($i = 0; $i < 15; $i++) {
        sleep(2);
        if (@file_get_contents($debounce_ref) !== $my_token) {
            exit(0);
        }
        if (netbird_connected()) {
            exit(0);
        }
    }
    if (!netbird_iface_exists(netbird_wg_iface())) {
        log_msg('NetBird CARP: not connected after 30s, retrying NetBird up');
        mwexecfm('/usr/local/sbin/configctl netbird up');
    }
} else {
    log_msg("NetBird CARP: node is BACKUP, stopping NetBird's WireGuard interface");
    netbird_down_converge();
}
