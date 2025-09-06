#!/usr/local/bin/php
<?php

/*
 *    Copyright (C) 2025 Frank Wall
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

require_once('script/load_phalcon.php');

use OPNsense\Core\Config;
use OPNsense\Trust\Cert;
use OPNsense\Trust\Store as CertStore;

$cert_filename = '/usr/local/etc/turnserver_cert.pem';
$pkey_filename = '/usr/local/etc/turnserver_pkey.pem';

$configObj = Config::getInstance()->object();
if (isset($configObj->OPNsense->turnserver->settings->TlsCertificate) and !empty((string)$configObj->OPNsense->turnserver->settings->TlsCertificate)) {
    $cert_refid = (string)$configObj->OPNsense->turnserver->settings->TlsCertificate;
    foreach ((new Cert())->cert->iterateItems() as $cert) {
        $refid = (string)$cert->refid;

        if ($cert_refid == $refid) {
            $cert_content = str_replace("\n\n", "\n", str_replace("\r", "", base64_decode((string)$cert->crt)));
            $pkey_content = str_replace("\n\n", "\n", str_replace("\r", "", base64_decode((string)$cert->prv)));

            if (!empty((string)$cert->caref)) {
                $ca = CertStore::getCaChain((string)$cert->caref);
                if ($ca) {
                    $cert_content .= "\n" . $ca;
                }
            }

            file_put_contents($cert_filename, $cert_content);
            file_put_contents($pkey_filename, $pkey_content);
            chmod($pkey_filename, 0600);
        }
    }
}
