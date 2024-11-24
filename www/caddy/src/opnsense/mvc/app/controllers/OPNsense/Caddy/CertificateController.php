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

namespace OPNsense\Caddy;

use OPNsense\Trust\Ca;
use OPNsense\Trust\Cert;
use OPNsense\Trust\Store as CertStore;

/**
 * Class CertificateController
 * @package OPNsense\Caddy
 */
class CertificateController
{
    private $tempDir;

    public function __construct($tempDir)
    {
        $this->tempDir = $tempDir;
    }

    private function writeFileIfChanged($filePath, $content)
    {
        if (
            !file_exists($filePath) || 
            hash('sha256', $content) !== hash_file('sha256', $filePath)
        ) {
            file_put_contents($filePath, $content);
        }
    }

    public function processCertificates()
    {
        foreach ((new Cert())->cert->iterateItems() as $cert) {
            $certChain = base64_decode((string)$cert->crt);
            $certKey = base64_decode((string)$cert->prv);

            if (!empty((string)$cert->caref)) {
                $ca = CertStore::getCACertificate((string)$cert->caref);
                if ($ca) {
                    $certChain .= "\n" . $ca['cert'];
                }
            }

            $this->writeFileIfChanged($this->tempDir . (string)$cert->refid . '.pem', $certChain);
            $this->writeFileIfChanged($this->tempDir . (string)$cert->refid . '.key', $certKey);
        }
    }

    public function processCaCertificates()
    {
        foreach ((new Ca())->ca->iterateItems() as $caItem) {
            $this->writeFileIfChanged(
                $this->tempDir . (string)$caItem->refid . '.pem',
                base64_decode((string)$caItem->crt)
            );
        }
    }

    public function processOpenVpnKeys()
    {
        foreach ((new Caddy())->reverseproxy->layer4openvpn->iterateItems() as $openvpnItem) {
            $this->writeFileIfChanged(
                $this->tempDir . (string) $openvpnItem->getAttributes()['uuid'] . '.key',
                (string) $openvpnItem->StaticKey
            );
        }
    }
}
