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
 * packet filter after the tunnel interface is recreated.
 *
 * When NetBird restarts, it destroys the existing tunnel interface,
 * creates a fresh tun device, and renames it.  This is particularly
 * important after a WAN failover with a default gateway switch, where
 * NetBird is restarted to bind to the new path.
 *
 * This script:
 *   1. Runs `/usr/local/etc/rc.d/netbird restart` (the real rc.d restart).
 *   2. Waits for the old tunnel interface to be torn down.  Reloading
 *      before that would attach the rules to the interface the restart is
 *      about to destroy, silently recreating the detached-rules bug.
 *   3. Waits for the new tunnel interface and reloads the packet filter
 *      via netbird_sync_filter().
 */

require_once("config.inc");
require_once("util.inc");
require_once("plugins.inc.d/netbird.inc");

$wt_iface = netbird_wg_iface();

// --- Restart the NetBird service -------------------------------------------
log_msg("NetBird: Restarting service");
mwexecfb('/usr/local/etc/rc.d/netbird restart');

netbird_wait_iface_gone($wt_iface);

// On a CARP BACKUP node the tunnel intentionally stays down after the
// restart (carp_guard start_postcmd); don't wait for an interface that
// will not appear.
if (netbird_carp_enabled() && !netbird_carp_check_master()) {
    log_msg("NetBird: CARP BACKUP node, tunnel stays down after restart, skipping packet filter sync");
    exit(0);
}

netbird_sync_filter($wt_iface);

exit(0);
