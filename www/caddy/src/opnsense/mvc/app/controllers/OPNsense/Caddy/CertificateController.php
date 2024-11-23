<?php

namespace OPNsense\Caddy;

use OPNsense\Trust\Ca;
use OPNsense\Trust\Cert;
use OPNsense\Trust\Store as CertStore;

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
                    $certChain .= "\n" . $ca['crt'];
                    if (!empty($ca['caref'])) {
                        $parentCa = CertStore::getCACertificate($ca['caref']);
                        if ($parentCa) {
                            $certChain .= "\n" . $parentCa['crt'];
                        }
                    }
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

