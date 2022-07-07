#!/usr/local/bin/php
<?php

/*
 *    Copyright (C) 2020 Deciso B.V.
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 */

require_once('plugins.inc');
require_once('config.inc');
require_once('certs.inc');
require_once("legacy_bindings.inc");

use OPNsense\Stunnel\Stunnel;
use OPNsense\Core\Config;

$base_path = "/usr/local/etc/stunnel/certs";
$stunnel = new Stunnel();
$configObj = Config::getInstance()->object();
$all_certs = [];
foreach ($stunnel->services->service->iterateItems() as $service) {
    if (!empty((string)$service->enabled)) {
        $this_uuid = $service->getAttributes()['uuid'];
        $srv_certid = (string)$service->servercert;
        foreach ($configObj->cert as $cert) {
            if ($srv_certid == (string)$cert->refid) {
                $all_certs["{$base_path}/{$this_uuid}.crt"] = base64_decode((string)$cert->crt);
                $certArr = (array)$cert;
                $chain = ca_chain($certArr);
                if (!empty($chain)) {
                    $all_certs["{$base_path}/{$this_uuid}.crt"] .= "\n" . $chain;
                }
                $all_certs["{$base_path}/{$this_uuid}.crt"] .= "\n" . base64_decode((string)$cert->prv);
            }
        }
        if (!empty((string)$service->cacert)) {
            $all_certs["{$base_path}/{$this_uuid}.ca"] = "";
            foreach (explode(",", (string)$service->cacert) as $caid) {
                foreach ($configObj->ca as $ca) {
                    if ((string)$ca->refid == $caid) {
                        $all_certs["{$base_path}/{$this_uuid}.ca"] .= base64_decode((string)$ca->crt) . "\n";
                    }
                }
            }
        }
    }
}

if (!is_dir("/usr/local/etc/stunnel/certs")) {
    mkdir("/usr/local/etc/stunnel/certs", 0700, true);
    chown("/usr/local/etc/stunnel/certs", "stunnel");
    chgrp("/usr/local/etc/stunnel/certs", "stunnel");
}

// cleanup stunnel cert directory
foreach (glob("{$base_path}/*") as $filename) {
    if (!isset($all_certs[$filename])) {
        unlink($filename);
    }
}

foreach ($all_certs as $filename => $content) {
    file_put_contents($filename, $content);
    chown($filename, "stunnel");
}

// trigger certificate revocation lists update
plugins_configure('crl');
