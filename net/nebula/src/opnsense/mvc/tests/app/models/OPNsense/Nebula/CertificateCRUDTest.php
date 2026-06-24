<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
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

namespace tests\OPNsense\Nebula;

use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;
use OPNsense\Nebula\Nebula;

/**
 * Model-level tests for the CertificateController data path.
 *
 * These tests exercise the same Add / validate / serializeToConfig / del
 * logic that signAction() and importAction() use, but at the model layer
 * (no configd / Backend in play — the live-guest script covers those paths).
 *
 * Specifically:
 *   - certificate node created with correct field values (origin, caref, fields)
 *   - caref stores and reads back the CA authority UUID
 *   - model validation rejects missing descr
 *   - del removes the certificate
 *   - search columns (descr, origin, caref, networks) are readable
 *
 * Note on ModelRelationField validation: the static option-list cache means the
 * caref validator cannot see in-memory Add()s.  We test UUID storage/readback
 * directly rather than asserting performValidation() == 0 for relation fields.
 */
class CertificateCRUDTest extends \PHPUnit\Framework\TestCase
{
    private static $configDir = __DIR__ . '/NebulaConfig';

    public static function setUpBeforeClass(): void
    {
        (new AppConfig())->update('application.configDir', self::$configDir);
        Config::getInstance()->forceReload();
    }

    /** Locate an ArrayField item by UUID, returning null if not found. */
    private function findByUuid($arrayField, $uuid)
    {
        foreach ($arrayField->iterateItems() as $node) {
            if ($node->getAttribute('uuid') === $uuid) {
                return $node;
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // signAction() model path
    // -------------------------------------------------------------------------

    public function testSignStoresAllFields()
    {
        // Simulate what signAction() does after configd returns crt+key.
        $mdl = new Nebula();

        // Add a CA authority so we have a real UUID for caref.
        $ca = $mdl->pki->authorities->authority->Add();
        $ca->descr  = 'sign-test-ca';
        $ca->crt    = 'CA_CRT';
        $ca->key    = 'CA_KEY';
        $caUuid = $ca->getAttribute('uuid');
        $this->assertNotEmpty($caUuid);

        // Now simulate what signAction() does.
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr           = 'sign-test-cert';
        $cert->origin          = 'signed';
        $cert->caref           = $caUuid;
        $cert->crt             = "-----BEGIN NEBULA CERTIFICATE-----\nfakecrt\n-----END NEBULA CERTIFICATE-----\n";
        $cert->key             = "-----BEGIN NEBULA ED25519 PRIVATE KEY-----\nfakekey\n-----END NEBULA ED25519 PRIVATE KEY-----\n";
        $cert->networks        = '10.10.0.1/24';
        $cert->groups          = 'lighthouse';
        $cert->unsafe_networks = '';

        $certUuid = $cert->getAttribute('uuid');
        $this->assertNotEmpty($certUuid, 'Add() must assign a UUID');

        // Verify field storage.
        $this->assertEquals('sign-test-cert', (string)$cert->descr);
        $this->assertEquals('signed', (string)$cert->origin);
        $this->assertEquals($caUuid, (string)$cert->caref, 'caref must store the CA UUID');
        $this->assertNotEmpty((string)$cert->crt);
        $this->assertNotEmpty((string)$cert->key);
        $this->assertEquals('10.10.0.1/24', (string)$cert->networks);
        $this->assertEquals('lighthouse', (string)$cert->groups);
    }

    public function testSignDefaultOriginIsSigned()
    {
        $mdl  = new Nebula();
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr = 'origin-check';

        $this->assertEquals('signed', (string)$cert->origin, 'Default origin must be signed');
    }

    // -------------------------------------------------------------------------
    // importAction() model path
    // -------------------------------------------------------------------------

    public function testImportStoresOriginImported()
    {
        // Simulate what importAction() does after pki_print_cert accepts the crt.
        $mdl  = new Nebula();
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr    = 'imported-cert';
        $cert->origin   = 'imported';
        $cert->crt      = 'IMPORTED_HOST_CRT';
        $cert->networks = '10.10.0.5/24';
        $cert->groups   = 'servers';
        // key intentionally omitted.

        $uuid = $cert->getAttribute('uuid');
        $this->assertNotEmpty($uuid);
        $this->assertEquals('imported', (string)$cert->origin);
        $this->assertEquals('IMPORTED_HOST_CRT', (string)$cert->crt);
        $this->assertEquals('10.10.0.5/24', (string)$cert->networks);
        $this->assertEquals('servers', (string)$cert->groups);
    }

    public function testImportWithKeyAndCaref()
    {
        $mdl = new Nebula();

        // CA authority for the caref.
        $ca = $mdl->pki->authorities->authority->Add();
        $ca->descr  = 'import-ca';
        $ca->crt    = 'CA_CRT';
        $caUuid = $ca->getAttribute('uuid');

        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr  = 'imported-with-key';
        $cert->origin = 'imported';
        $cert->caref  = $caUuid;
        $cert->crt    = 'HOST_CRT';
        $cert->key    = 'HOST_KEY';

        $this->assertEquals($caUuid, (string)$cert->caref, 'caref must store the CA UUID');
        $this->assertEquals('HOST_KEY', (string)$cert->key);
    }

    // -------------------------------------------------------------------------
    // Validation guards
    // -------------------------------------------------------------------------

    public function testValidationRejectsMissingDescr()
    {
        // signAction() and importAction() both guard for empty descr before calling
        // Add().  Belt-and-suspenders: verify performValidation() also catches it.
        $mdl  = new Nebula();
        $cert = $mdl->pki->certificates->certificate->Add();
        // descr intentionally left empty.

        $msgs  = $mdl->performValidation();
        $found = false;
        foreach ($msgs as $m) {
            if (strpos($m->getField(), 'descr') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Missing required descr must produce a validation error');
    }

    // -------------------------------------------------------------------------
    // Del
    // -------------------------------------------------------------------------

    public function testDelRemovesCertificate()
    {
        $mdl  = new Nebula();
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr = 'del-cert-test';
        $cert->crt   = 'CRT';
        $cert->key   = 'KEY';

        $uuid = $cert->getAttribute('uuid');
        $this->assertNotNull($this->findByUuid($mdl->pki->certificates->certificate, $uuid));

        $result = $mdl->pki->certificates->certificate->del($uuid);
        $this->assertTrue($result, 'del() must return true for a found certificate');
        $this->assertNull(
            $this->findByUuid($mdl->pki->certificates->certificate, $uuid),
            'Certificate must be gone after del()'
        );
    }

    // -------------------------------------------------------------------------
    // searchItem columns (descr, origin, caref, networks)
    // -------------------------------------------------------------------------

    public function testSearchColumnsAreReadable()
    {
        // searchItemAction() exposes [descr, ca_name, networks, valid_to].
        // Verify these computed display fields exist and are readable.
        $mdl  = new Nebula();
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr    = 'search-test-cert';
        $cert->ca_name  = 'demo-ca';
        $cert->networks = '10.0.0.2/24';
        $cert->valid_to = '2027-06-05T00:00:00Z';

        $this->assertEquals('search-test-cert',     (string)$cert->descr);
        $this->assertEquals('demo-ca',              (string)$cert->ca_name);
        $this->assertEquals('10.0.0.2/24',          (string)$cert->networks);
        $this->assertEquals('2027-06-05T00:00:00Z', (string)$cert->valid_to);
        // caref defaults to empty when not set.
        $this->assertEquals('', (string)$cert->caref);
    }

    public function testComputedFieldsArePersisted()
    {
        $mdl  = new Nebula();
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr       = 'computed-cert';
        $cert->cn          = 'host1';
        $cert->ca_name     = 'demo-ca';
        $cert->valid_from  = '2026-06-05T00:00:00Z';
        $cert->valid_to    = '2027-06-05T00:00:00Z';
        $cert->fingerprint = 'cafebabe';
        $cert->is_ca       = '0';
        $cert->has_key     = '1';

        $this->assertEquals('host1',                 (string)$cert->cn);
        $this->assertEquals('demo-ca',               (string)$cert->ca_name);
        $this->assertEquals('cafebabe',              (string)$cert->fingerprint);
        $this->assertEquals('0',                     (string)$cert->is_ca);
        $this->assertEquals('1',                     (string)$cert->has_key);
    }

    // -------------------------------------------------------------------------
    // issuer (signing CA fingerprint) + curve + cert-only has_key
    // -------------------------------------------------------------------------

    /**
     * The cert stores the signing CA's fingerprint in `issuer` (set by
     * storeComputedFields from details.issuer).  searchItemAction resolves the
     * CA's display name dynamically from this, so we never rely on a stored
     * ca_name.  Here we verify the field round-trips.
     */
    public function testIssuerFingerprintIsStored()
    {
        $mdl  = new Nebula();
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr  = 'issuer-cert';
        $cert->issuer = '06a5719971755f60540408916f37af0c165fe4d5a31cc8a2a4848a461889ed06';

        $this->assertEquals(
            '06a5719971755f60540408916f37af0c165fe4d5a31cc8a2a4848a461889ed06',
            (string)$cert->issuer
        );
    }

    /**
     * The cert model carries a curve field (populated from info[0].curve at
     * sign/import) so the Host Certificates grid can show a Curve column.
     */
    public function testCurveFieldIsStored()
    {
        $mdl  = new Nebula();
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr = 'curve-cert';
        $cert->curve = 'P256';

        $this->assertEquals('P256', (string)$cert->curve);

        $cert2 = $mdl->pki->certificates->certificate->Add();
        $cert2->descr = 'default-curve-cert';
        $this->assertEquals('25519', (string)$cert2->curve, 'curve defaults to 25519');
    }

    /**
     * A cert-only import (no private key) must have has_key='0' so no
     * Download-key button is shown for it.
     */
    public function testCertOnlyImportHasNoKey()
    {
        $mdl  = new Nebula();
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr   = 'cert-only';
        $cert->origin  = 'imported';
        $cert->crt     = 'HOST_CRT';
        $cert->has_key = '0';

        $this->assertEquals('0', (string)$cert->has_key, 'cert-only import must have has_key=0');
    }

    public function testCertRetrievableByUuid()
    {
        $mdl  = new Nebula();
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr    = 'lookup-cert';
        $cert->networks = '192.168.50.10/24';

        $uuid  = $cert->getAttribute('uuid');
        $found = $this->findByUuid($mdl->pki->certificates->certificate, $uuid);
        $this->assertNotNull($found, 'Certificate must be retrievable by UUID');
        $this->assertEquals('lookup-cert',       (string)$found->descr);
        $this->assertEquals('192.168.50.10/24',  (string)$found->networks);
    }

    // -------------------------------------------------------------------------
    // certReferencedBy + purgeExpiredCertificates (the data path
    // CertificateController::delItemAction / purgeExpiredAction share)
    // -------------------------------------------------------------------------

    /** Add a certificate with the given description and notAfter. */
    private function addCert(Nebula $mdl, string $descr, string $validTo): string
    {
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr    = $descr;
        $cert->valid_to = $validTo;
        return $cert->getAttribute('uuid');
    }

    public function testCertReferencedByDetectsInstance()
    {
        $mdl = new Nebula();
        $unrefUuid = $this->addCert($mdl, 'unref-cert', '2027-01-01T00:00:00Z');
        $usedUuid  = $this->addCert($mdl, 'used-cert',  '2027-01-01T00:00:00Z');

        $inst = $mdl->instances->instance->Add();
        $inst->description = 'gw';
        $inst->certref     = $usedUuid;

        $this->assertNull($mdl->certReferencedBy($unrefUuid), 'unreferenced cert → null');
        $this->assertStringContainsString('gw', (string)$mdl->certReferencedBy($usedUuid));
    }

    public function testPurgeExpiredCertificatesSkipsReferenced()
    {
        $mdl = new Nebula();
        $expiredUnref = $this->addCert($mdl, 'old-unref', '2000-01-01T00:00:00Z');
        $current      = $this->addCert($mdl, 'current',   '2999-01-01T00:00:00Z');
        $expiredRef   = $this->addCert($mdl, 'old-inuse', '2000-01-01T00:00:00Z');

        // An instance references the expired-in-use cert.
        $inst = $mdl->instances->instance->Add();
        $inst->description = 'router';
        $inst->certref     = $expiredRef;

        $res = $mdl->purgeExpiredCertificates();

        $this->assertEquals(1, $res['removed'], 'only the expired unreferenced cert is removed');
        $this->assertEquals(['old-inuse'], $res['skippedNames'], 'expired-but-referenced cert reported');
        $this->assertNull(
            $this->findByUuid($mdl->pki->certificates->certificate, $expiredUnref),
            'expired unreferenced cert must be gone'
        );
        $this->assertNotNull(
            $this->findByUuid($mdl->pki->certificates->certificate, $current),
            'current cert must remain'
        );
        $this->assertNotNull(
            $this->findByUuid($mdl->pki->certificates->certificate, $expiredRef),
            'expired referenced cert must be kept'
        );
    }
}
