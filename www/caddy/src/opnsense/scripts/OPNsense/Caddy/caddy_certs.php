#!/usr/local/bin/php
<?php

/*
 *    Copyright (C) 2024-2026 Cedrik Pischem
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

use OPNsense\Caddy\Caddy;
use OPNsense\Trust\Ca;
use OPNsense\Trust\Cert;
use OPNsense\Trust\Store as CertStore;

$writeFileIfChanged = function ($filePath, $content) {
    if (
        !file_exists($filePath) ||
        hash('sha256', $content) !== hash_file('sha256', $filePath)
    ) {
        file_put_contents($filePath, $content);
    }
};

/* XXX used later to only append a file name */
$tempDir = '/usr/local/etc/caddy/certificates/';

// leaf certificate chain
$certificateRefs = [];

$caddyMdl = new Caddy();

foreach ($caddyMdl->reverseproxy->reverse->iterateItems() as $reverseItem) {
    if (!$reverseItem->CustomCertificate->isEmpty()) {
        $certificateRefs[] = $reverseItem->CustomCertificate->getValue();
    }
}

$certificateRefs = array_unique($certificateRefs);

foreach ((new Cert())->cert->iterateItems() as $cert) {
    $refid = $cert->refid->getValue();

    if (in_array($refid, $certificateRefs, true)) {
        $certChain = base64_decode($cert->crt->getValue());
        $certKey = base64_decode($cert->prv->getValue());

        if (!$cert->caref->isEmpty()) {
            $ca = CertStore::getCaChain($cert->caref->getValue());
            if ($ca) {
                $certChain .= "\n" . $ca;
            }
        }

        $writeFileIfChanged($tempDir . $refid . '.pem', $certChain);
        $writeFileIfChanged($tempDir . $refid . '.key', $certKey);
    }
}

// ca certificate
$caCertRefs = [];

foreach ($caddyMdl->reverseproxy->handle->iterateItems() as $handleItem) {
    if (!$handleItem->HttpTlsTrustedCaCerts->isEmpty()) {
        $caCertRefs[] = $handleItem->HttpTlsTrustedCaCerts->getValue();
    }
}

foreach ($caddyMdl->reverseproxy->reverse->iterateItems() as $reverseItem) {
    foreach (explode(',', $reverseItem->ClientAuthTrustPool->getValue()) as $ref) {
        if (!empty($ref)) {
            $caCertRefs[] = $ref;
        }
    }
}

foreach ($caddyMdl->reverseproxy->subdomain->iterateItems() as $subdomainItem) {
    foreach (explode(',', $subdomainItem->ClientAuthTrustPool->getValue()) as $ref) {
        if (!empty($ref)) {
            $caCertRefs[] = $ref;
        }
    }
}

$caCertRefs = array_unique($caCertRefs);

foreach ((new Ca())->ca->iterateItems() as $caItem) {
    $refid = $caItem->refid->getValue();
    if (in_array($refid, $caCertRefs, true)) {
        $caCert = base64_decode($caItem->crt->getValue());
        $writeFileIfChanged($tempDir . $refid . '.pem', $caCert);
    }
}

// openvpn static keys
foreach ($caddyMdl->reverseproxy->layer4openvpn->iterateItems() as $openvpnItem) {
    $writeFileIfChanged(
        $tempDir . $openvpnItem->getAttributes()['uuid'] . '.key',
        $openvpnItem->StaticKey->getValue()
    );
}
