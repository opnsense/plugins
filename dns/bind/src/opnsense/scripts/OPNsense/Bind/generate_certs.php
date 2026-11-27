#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 opnsense.org community
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

require_once("script/load_phalcon.php");

use OPNsense\Core\Config;
use OPNsense\Core\File;
use OPNsense\Trust\Store;

$targetdir = "/var/etc/named";
$certfile  = "{$targetdir}/dot.crt";
$keyfile   = "{$targetdir}/dot.key";

if (!is_dir($targetdir)) {
    mkdir($targetdir, 0750, true);
    chown($targetdir, 'bind');
    chgrp($targetdir, 'bind');
}

$cfg     = Config::getInstance()->object();
$general = $cfg->OPNsense->bind->general ?? new \stdClass();

$dotenable = (string)($general->dotenable ?? '0');
$certref   = (string)($general->dotcertificate ?? '');

if ($dotenable === '1' && $certref !== '') {
    $cert = Store::getCertificate($certref);
    if ($cert && isset($cert['prv'])) {
        File::file_update_contents($certfile, $cert['crt'], 0640);
        File::file_update_contents($keyfile,  $cert['prv'], 0640);
        chown($certfile, 'bind');
        chgrp($certfile, 'bind');
        chown($keyfile, 'bind');
        chgrp($keyfile, 'bind');
        exit(0);
    }
}

foreach ([$certfile, $keyfile] as $f) {
    if (file_exists($f)) {
        unlink($f);
    }
}
