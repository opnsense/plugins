#!/usr/local/bin/php
<?php

/*
 *    Copyright (C) 2024 Cedrik Pischem
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

$tempDir = '/usr/local/etc/caddy/certificates';

// leaf certificate chain
$certificateRefs = [];

foreach ((new Caddy())->reverseproxy->reverse->iterateItems() as $reverseItem) {
    $certRef = (string)$reverseItem->CustomCertificate;
    if (!empty($certRef)) {
        $certificateRefs[] = $certRef;
    }
}

$certificateRefs = array_unique($certificateRefs);

foreach ((new Cert())->cert->iterateItems() as $cert) {
    $refid = (string)$cert->refid;

    if (in_array($refid, $certificateRefs, true)) {
        $certChain = base64_decode((string)$cert->crt);
        $certKey = base64_decode((string)$cert->prv);

        if (!empty((string)$cert->caref)) {
            $ca = CertStore::getCaChain((string)$cert->caref);
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

foreach ((new Caddy())->reverseproxy->handle->iterateItems() as $handleItem) {
    $caCertField = (string)$handleItem->HttpTlsTrustedCaCerts;

    if (!empty($caCertField)) {
        $caCertRefs[] = $caCertField;
    }
}

foreach ((new Caddy())->reverseproxy->reverse->iterateItems() as $reverseItem) {
    $caCertField = (string)$reverseItem->ClientAuthTrustPool;

    if (!empty($caCertField)) {
        $refs = array_map('trim', explode(',', $caCertField));
        foreach ($refs as $ref) {
            if (!empty($ref)) {
                $caCertRefs[] = $ref;
            }
        }
    }
}

foreach ((new Caddy())->reverseproxy->subdomain->iterateItems() as $subdomainItem) {
    $caCertField = (string)$subdomainItem->ClientAuthTrustPool;

    if (!empty($caCertField)) {
        $refs = array_map('trim', explode(',', $caCertField));
        foreach ($refs as $ref) {
            if (!empty($ref)) {
                $caCertRefs[] = $ref;
            }
        }
    }
}

$caCertRefs = array_unique($caCertRefs);

foreach ((new Ca())->ca->iterateItems() as $caItem) {
    $refid = (string)$caItem->refid;
    if (in_array($refid, $caCertRefs, true)) {
        $caCert = base64_decode((string)$caItem->crt);
        $writeFileIfChanged($tempDir . $refid . '.pem', $caCert);
    }
}

// openvpn static keys
foreach ((new Caddy())->reverseproxy->layer4openvpn->iterateItems() as $openvpnItem) {
    $writeFileIfChanged(
        $tempDir . (string)$openvpnItem->getAttributes()['uuid'] . '.key',
        (string)$openvpnItem->StaticKey
    );
}
