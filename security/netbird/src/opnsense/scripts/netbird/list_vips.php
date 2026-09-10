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
 * Feeds the "CARP VIP to Track" dropdown (carpVipTrack, a
 * JsonKeyValueStoreField).  Emits the static "any"/"all" modes plus one
 * entry per configured CARP VIP, keyed as "<vhid>@<device>" — the same
 * format the rc.syshook.d/carp subsystem argument uses, so the stored
 * value can be compared to CARP events directly.
 */

require_once('config.inc');

$result = [
    'any' => gettext('Any CARP VIP'),
    'all' => gettext('All CARP VIPs'),
];

$config = OPNsense\Core\Config::getInstance()->object();

if (isset($config->virtualip->vip)) {
    foreach ($config->virtualip->vip as $vip) {
        if ((string)$vip->mode !== 'carp') {
            continue;
        }
        $ifname = (string)$vip->interface;
        if (!isset($config->interfaces->$ifname->if)) {
            continue;
        }
        $device = (string)$config->interfaces->$ifname->if;
        $ifdescr = (string)$config->interfaces->$ifname->descr;
        if ($ifdescr === '') {
            $ifdescr = strtoupper($ifname);
        }
        $key = (string)$vip->vhid . '@' . $device;
        $result[$key] = sprintf(
            '%s: %s vhid %s (%s)',
            $ifdescr,
            (string)$vip->subnet,
            (string)$vip->vhid,
            $device
        );
    }
}

echo json_encode($result) . PHP_EOL;
