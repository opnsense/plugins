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
 * CARP start guard for the NetBird rc.d service.
 *
 * Runs as start_postcmd (injected through /etc/rc.conf.d/netbird by the
 * plugin template when CARP failover support is enabled).  After the
 * daemon starts — at boot, after an HA config-sync service restart, or a
 * manual service start — this makes sure a CARP BACKUP node does not keep
 * an active NetBird connection, replacing the rc script patch previously
 * proposed in opnsense/ports#259.
 *
 * The MASTER check is a quick ifconfig scan; if the tunnel must be torn
 * down, "netbird down" is spawned in the background so the rc start path
 * (and the boot sequence) is never delayed.  Always exits 0: a failing
 * start_postcmd would make run_rc_command report a start failure even
 * though the daemon is running.
 */

require_once('config.inc');
require_once('util.inc');
require_once('plugins.inc.d/netbird.inc');

if (!netbird_carp_check_master()) {
    log_msg('NetBird: CARP BACKUP node detected after service start, disconnecting NetBird');
    mwexecfb('/usr/local/bin/netbird down');
}

exit(0);
