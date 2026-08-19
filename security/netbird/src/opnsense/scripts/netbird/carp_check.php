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

/**
 * CARP state probe for NetBird HA handling.
 *
 * Returns exit code 0 if NetBird may connect:
 *   - CARP mode not enabled for NetBird, OR
 *   - Current host is CARP MASTER
 *
 * Returns exit code 1 otherwise:
 *   - CARP mode enabled and current host is BACKUP
 *
 * The service start path itself is guarded by carp_guard.php, which the
 * plugin injects as start_postcmd via /etc/rc.conf.d/netbird; this script
 * remains a standalone probe for scripts and manual troubleshooting.
 */

require_once('config.inc');
require_once('plugins.inc.d/netbird.inc');

if (netbird_carp_check_master()) {
    exit(0);
}

exit(1);
