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
 */

require_once("config.inc");
require_once("util.inc");

$subsystem = !empty($argv[1]) ? $argv[1] : 'unknown';
$type = !empty($argv[2]) ? $argv[2] : 'unknown';

$debounce_ref = '/tmp/tmp_netbird_carp_event.tmp';

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

// We are the last event in the burst — proceed
log_msg("NetBird CARP: '{$type}' event from '{$subsystem}', appears to be the last event in burst, processing");
switch ($type) {
    case 'MASTER':
        log_msg("NetBird CARP: '{$type}' event from '{$subsystem}', starting NetBird's WireGuard interface");
        mwexecfm('/usr/local/sbin/configctl netbird up');
        break;
    case 'BACKUP':
        log_msg("NetBird CARP: '{$type}' event from '{$subsystem}', stopping NetBird's WireGuard interface");
        mwexecfm('/usr/local/sbin/configctl netbird down');
        break;
}

@unlink($debounce_ref);
