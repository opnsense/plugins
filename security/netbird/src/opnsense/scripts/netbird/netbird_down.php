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
 * Wrapper script for "netbird down" that verifies the disconnect actually
 * happened.  A one-shot "netbird down" fired right after a service start
 * (boot, HA config sync, GUI restart) races the daemon socket coming up
 * and is silently lost, leaving a CARP BACKUP node connected.
 *
 * All callers that must guarantee a disconnect (configd [down] action,
 * carp_guard.php) point here; the retry/verify loop lives in
 * netbird_down_converge().  An optional numeric argument keeps watching
 * that many seconds after the disconnect and downs the daemon again if it
 * restores its previous "up" state on its own (service start scenario).
 */

require_once("config.inc");
require_once("util.inc");
require_once("plugins.inc.d/netbird.inc");

$watch = !empty($argv[1]) ? (int)$argv[1] : 0;

if (netbird_down_converge(10, 2, $watch)) {
    echo "NetBird disconnected\n";
    exit(0);
}

echo "NetBird still connected, disconnect failed\n";
exit(1);
