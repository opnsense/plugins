#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Andrew Watkins
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
 * Export the certificates used for AWS certificate based authentication (IAM Roles Anywhere,
 * AWS IoT credential provider) from the trust store, so the native backend can read them.
 */

require_once("script/load_phalcon.php");

use OPNsense\Core\File;
use OPNsense\DynDNS\DynDNS;
use OPNsense\Trust\Store;

$targetdir = '/var/etc/ddclient';
$filenames = [];

if ((!is_dir($targetdir) && !mkdir($targetdir, 0700, true)) || !chmod($targetdir, 0700)) {
    syslog(LOG_ERR, 'ddclient: unable to prepare the certificate export directory');
    exit(1);
}

foreach ((new DynDNS())->accounts->account->iterateItems() as $uuid => $account) {
    if (
        $account->enabled->isEmpty() ||
        !$account->service->isEqual('aws') ||
        !in_array((string)$account->aws_auth, ['rolesanywhere', 'iot']) ||
        $account->aws_certificate->isEmpty()
    ) {
        continue;
    }
    $cert = Store::getCertificate((string)$account->aws_certificate);
    if ($cert === false || empty($cert['prv'])) {
        syslog(LOG_ERR, sprintf('ddclient: certificate for account %s not found or without private key', $uuid));
        continue;
    }
    $files = ["{$targetdir}/{$uuid}.crt" => $cert['crt'], "{$targetdir}/{$uuid}.key" => $cert['prv']];
    if (!empty($cert['ca']['crt'])) {
        $files["{$targetdir}/{$uuid}.ca"] = $cert['ca']['crt'];
    }
    foreach ($files as $filename => $content) {
        if (File::file_update_contents($filename, $content, 0600) === false) {
            syslog(LOG_ERR, sprintf('ddclient: unable to export certificate material for account %s', $uuid));
            exit(1);
        }
        // file_update_contents skips unchanged files, including their mode.
        if (!chmod($filename, 0600)) {
            syslog(LOG_ERR, sprintf('ddclient: unable to secure certificate material for account %s', $uuid));
            exit(1);
        }
        $filenames[] = $filename;
    }
}

// remove certificates of deleted or reconfigured accounts
foreach (glob("{$targetdir}/*.{crt,key,ca}", GLOB_BRACE) as $filename) {
    if (!in_array($filename, $filenames)) {
        unlink($filename);
    }
}
