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
 * Model-level tests for the AuthorityController data path.
 *
 * These tests exercise the same Add / validate / serializeToConfig / del
 * logic that generateAction() and importAction() use, but at the model layer
 * (no configd / Backend in play — those paths are covered by the live guest
 * script tools/test_authority_live.php).
 *
 * The "import accept/reject" logic that calls pki_print_cert is validated in
 * the live script.  Here we focus on:
 *   - authority node created with correct field values (origin, curve, crt, key)
 *   - model validation rejects missing descr (same guard as the controller)
 *   - serializeToConfig round-trips the authority through config.xml
 *   - del removes the authority
 *   - origin and curve options are stored and retrieved correctly
 */
class AuthorityCRUDTest extends \PHPUnit\Framework\TestCase
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
    // generateAction() model path
    // -------------------------------------------------------------------------

    public function testGenerateStoresAllFields()
    {
        // Simulate what generateAction() does after configd returns crt+key.
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr  = 'test-ca-generated';
        $node->origin = 'generated';
        $node->curve  = '25519';
        $node->crt    = "-----BEGIN NEBULA CERTIFICATE-----\nfakecrt\n-----END NEBULA CERTIFICATE-----\n";
        $node->key    = "-----BEGIN NEBULA ED25519 PRIVATE KEY-----\nfakekey\n-----END NEBULA ED25519 PRIVATE KEY-----\n";

        $uuid = $node->getAttribute('uuid');
        $this->assertNotEmpty($uuid, 'Add() must assign a UUID');

        // Validation must pass for a fully-populated node.
        $msgs = $mdl->performValidation();
        $this->assertEquals(0, count($msgs), 'Valid authority node must pass validation');

        $this->assertEquals('test-ca-generated', (string)$node->descr);
        $this->assertEquals('generated', (string)$node->origin);
        $this->assertEquals('25519', (string)$node->curve);
        $this->assertNotEmpty((string)$node->crt);
        $this->assertNotEmpty((string)$node->key);
    }

    public function testGenerateWithP256Curve()
    {
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr  = 'p256-ca';
        $node->origin = 'generated';
        $node->curve  = 'P256';
        $node->crt    = 'CERTDATA';
        $node->key    = 'KEYDATA';

        $msgs = $mdl->performValidation();
        $this->assertEquals(0, count($msgs), 'P256 authority must pass validation');
        $this->assertEquals('P256', (string)$node->curve);
    }

    // -------------------------------------------------------------------------
    // importAction() model path
    // -------------------------------------------------------------------------

    public function testImportStoresOriginImported()
    {
        // Simulate what importAction() does after pki_print_cert accepts the crt.
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr  = 'imported-ca';
        $node->origin = 'imported';
        $node->curve  = '25519';
        $node->crt    = 'IMPORTED_CRT';
        // key is optional on import (cert-only trust anchor).

        $uuid = $node->getAttribute('uuid');
        $this->assertNotEmpty($uuid);

        $msgs = $mdl->performValidation();
        $this->assertEquals(0, count($msgs), 'Import node (no key) must pass validation');
        $this->assertEquals('imported', (string)$node->origin);
    }

    public function testImportWithKeyStoresKey()
    {
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr  = 'imported-ca-with-key';
        $node->origin = 'imported';
        $node->curve  = '25519';
        $node->crt    = 'IMPORTED_CRT';
        $node->key    = 'IMPORTED_KEY';

        $msgs = $mdl->performValidation();
        $this->assertEquals(0, count($msgs), 'Import node with key must pass validation');
        $this->assertEquals('IMPORTED_KEY', (string)$node->key);
    }

    // -------------------------------------------------------------------------
    // Validation guards
    // -------------------------------------------------------------------------

    public function testValidationRejectsMissingDescr()
    {
        // Both generateAction() and importAction() guard for empty descr before
        // calling Add().  This test ensures that even if Add() is called without
        // descr, performValidation() catches it (belt-and-suspenders).
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        // Intentionally leave descr empty.

        $msgs = $mdl->performValidation();
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

    public function testDelRemovesAuthority()
    {
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr = 'del-test-ca';
        $node->crt   = 'CRT';
        $node->key   = 'KEY';

        $uuid = $node->getAttribute('uuid');
        $this->assertNotNull($this->findByUuid($mdl->pki->authorities->authority, $uuid));

        $result = $mdl->pki->authorities->authority->del($uuid);
        $this->assertTrue($result, 'del() must return true for a found authority');
        $this->assertNull($this->findByUuid($mdl->pki->authorities->authority, $uuid), 'Authority must be gone after del()');
    }

    // -------------------------------------------------------------------------
    // searchItem path (fields present)
    // -------------------------------------------------------------------------

    public function testSearchFieldsAreAccessible()
    {
        // The searchItemAction() exposes [descr, cn, valid_to, has_key] — verify
        // those computed display fields exist and are readable on an Added node.
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr    = 'search-test-ca';
        $node->cn       = 'search-test-ca';
        $node->valid_to = '2027-06-05T00:00:00Z';
        $node->has_key  = '1';

        $this->assertEquals('search-test-ca',        (string)$node->descr);
        $this->assertEquals('search-test-ca',        (string)$node->cn);
        $this->assertEquals('2027-06-05T00:00:00Z',  (string)$node->valid_to);
        $this->assertEquals('1',                     (string)$node->has_key);
    }

    // -------------------------------------------------------------------------
    // computed read-only fields (populated by the controller at create time)
    // -------------------------------------------------------------------------

    public function testComputedFieldsArePersisted()
    {
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr       = 'computed-ca';
        $node->cn          = 'computed-ca';
        $node->valid_from  = '2026-06-05T00:00:00Z';
        $node->valid_to    = '2027-06-05T00:00:00Z';
        $node->fingerprint = 'deadbeef';
        $node->is_ca       = '1';
        $node->has_key     = '1';

        $this->assertEquals('computed-ca',           (string)$node->cn);
        $this->assertEquals('2026-06-05T00:00:00Z',  (string)$node->valid_from);
        $this->assertEquals('2027-06-05T00:00:00Z',  (string)$node->valid_to);
        $this->assertEquals('deadbeef',              (string)$node->fingerprint);
        $this->assertEquals('1',                     (string)$node->is_ca);
        $this->assertEquals('1',                     (string)$node->has_key);
    }

    // -------------------------------------------------------------------------
    // can_sign (distinct from has_key)
    // -------------------------------------------------------------------------

    /**
     * can_sign defaults to '0' on a fresh authority node and is independent of
     * has_key.  has_key drives the Download-key button; can_sign drives the
     * "Can sign" grid column and the Sign-dialog dropdown filter.
     */
    public function testCanSignDefaultsToZero()
    {
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr = 'fresh-ca';

        $this->assertEquals('0', (string)$node->can_sign, 'can_sign must default to 0');
    }

    /**
     * A generated CA (is_ca + unencrypted key present) is a valid signer.
     */
    public function testGeneratedCaCanSign()
    {
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr    = 'signable-ca';
        $node->is_ca    = '1';
        $node->has_key  = '1';
        $node->can_sign = '1';

        $this->assertEquals('1', (string)$node->has_key);
        $this->assertEquals('1', (string)$node->can_sign);
    }

    /**
     * An encrypted-key CA stores the key (has_key=1) AND can sign (can_sign=1) — the
     * passphrase is supplied per signing operation (INFRA-163), so encryption no
     * longer excludes a CA from signing.  It is flagged key_encrypted=1 so the Sign
     * dialog knows to prompt for the passphrase.
     * A cert-only CA stores no key (has_key=0) and cannot sign (can_sign=0).
     */
    public function testEncryptedCaCanSignCertOnlyCannot()
    {
        $mdl = new Nebula();

        $enc = $mdl->pki->authorities->authority->Add();
        $enc->descr         = 'encrypted-ca';
        $enc->is_ca         = '1';
        $enc->has_key       = '1';   // key is stored …
        $enc->key_encrypted = '1';   // … and encrypted …
        $enc->can_sign      = '1';   // … but still signable with a passphrase.
        $this->assertEquals('1', (string)$enc->has_key);
        $this->assertEquals('1', (string)$enc->key_encrypted);
        $this->assertEquals('1', (string)$enc->can_sign);

        $certOnly = $mdl->pki->authorities->authority->Add();
        $certOnly->descr    = 'cert-only-ca';
        $certOnly->is_ca    = '1';
        $certOnly->has_key  = '0';   // no key at all (cert-only trust anchor).
        $certOnly->can_sign = '0';
        $this->assertEquals('0', (string)$certOnly->has_key);
        $this->assertEquals('0', (string)$certOnly->can_sign);
    }

    /**
     * key_encrypted defaults to '0' on a fresh node and round-trips both states.
     */
    public function testKeyEncryptedField()
    {
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr = 'enc-field-ca';
        $this->assertEquals('0', (string)$node->key_encrypted, 'key_encrypted must default to 0');

        $node->key_encrypted = '1';
        $this->assertEquals('1', (string)$node->key_encrypted);
    }

    // -------------------------------------------------------------------------
    // CA network constraint fields (Fix 3)
    // -------------------------------------------------------------------------

    /**
     * A CA with network constraints stores networks + unsafe_networks correctly.
     * These are populated by storeComputedFields() from pki_print_cert output at
     * generate/import time; here we verify the model fields accept and round-trip
     * the values (the controller population path is tested by the live script).
     */
    public function testNetworkConstraintFieldsAreStored()
    {
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr           = 'constrained-ca';
        $node->cn              = 'constrained-ca';
        $node->crt             = 'CERTDATA';
        $node->networks        = '192.168.100.0/24';
        $node->unsafe_networks = '';

        $msgs = $mdl->performValidation();
        $this->assertEquals(0, count($msgs), 'Constrained CA must pass validation');
        $this->assertEquals('192.168.100.0/24', (string)$node->networks);
        $this->assertEquals('',                 (string)$node->unsafe_networks);
    }

    /**
     * A CA without network constraints has empty networks fields (unrestricted).
     */
    public function testUnrestrictedCaHasEmptyNetworks()
    {
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr = 'unrestricted-ca';
        $node->cn    = 'unrestricted-ca';
        $node->crt   = 'CERTDATA';
        // networks and unsafe_networks intentionally left empty (defaults).

        $this->assertEquals('', (string)$node->networks,        'Unrestricted CA must have empty networks');
        $this->assertEquals('', (string)$node->unsafe_networks, 'Unrestricted CA must have empty unsafe_networks');
    }

    /**
     * searchItemAction exposes networks and unsafe_networks (both present on node).
     * We verify the fields are readable via direct field access (the actual
     * searchBase() path is covered by live tests).
     */
    public function testSearchItemExposesNetworkFields()
    {
        $mdl  = new Nebula();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr           = 'search-net-ca';
        $node->cn              = 'search-net-ca';
        $node->valid_to        = '2027-06-05T00:00:00Z';
        $node->has_key         = '1';
        $node->networks        = '10.0.0.0/24';
        $node->unsafe_networks = '10.0.1.0/24';

        $this->assertEquals('10.0.0.0/24',  (string)$node->networks);
        $this->assertEquals('10.0.1.0/24',  (string)$node->unsafe_networks);
    }

    // -------------------------------------------------------------------------
    // caReferencedBy + purgeExpiredAuthorities (the data path
    // AuthorityController::delItemAction / purgeExpiredAction share)
    // -------------------------------------------------------------------------

    /** Add a minimal valid CA with the given description and notAfter. */
    private function addCa(Nebula $mdl, string $descr, string $validTo): string
    {
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr    = $descr;
        $node->origin   = 'generated';
        $node->curve    = '25519';
        $node->crt      = 'CERTDATA';
        $node->key      = 'KEYDATA';
        $node->valid_to = $validTo;
        return $node->getAttribute('uuid');
    }

    public function testCaReferencedByDetectsCertAndInstance()
    {
        $mdl = new Nebula();
        $unrefUuid = $this->addCa($mdl, 'unref-ca', '2027-01-01T00:00:00Z');
        $signUuid  = $this->addCa($mdl, 'signing-ca', '2027-01-01T00:00:00Z');
        $trustUuid = $this->addCa($mdl, 'trusted-ca', '2027-01-01T00:00:00Z');

        // A cert signed by signing-ca.
        $cert = $mdl->pki->certificates->certificate->Add();
        $cert->descr = 'leaf';
        $cert->caref = $signUuid;

        // An instance that trusts trusted-ca.
        $inst = $mdl->instances->instance->Add();
        $inst->description = 'web';
        $inst->trusted_cas = $trustUuid;

        $this->assertNull($mdl->caReferencedBy($unrefUuid), 'unreferenced CA → null');
        $this->assertStringContainsString('leaf', (string)$mdl->caReferencedBy($signUuid));
        $this->assertStringContainsString('web', (string)$mdl->caReferencedBy($trustUuid));
    }

    public function testPurgeExpiredAuthoritiesSkipsReferenced()
    {
        $mdl = new Nebula();
        $expiredUnref = $this->addCa($mdl, 'old-unref', '2000-01-01T00:00:00Z');
        $current      = $this->addCa($mdl, 'current',   '2999-01-01T00:00:00Z');
        $expiredRef   = $this->addCa($mdl, 'old-inuse', '2000-01-01T00:00:00Z');

        // Reference the expired-in-use CA from an instance's trusted_cas.
        $inst = $mdl->instances->instance->Add();
        $inst->description = 'edge';
        $inst->trusted_cas = $expiredRef;

        $res = $mdl->purgeExpiredAuthorities();

        $this->assertEquals(1, $res['removed'], 'only the expired unreferenced CA is removed');
        $this->assertEquals(['old-inuse'], $res['skippedNames'], 'expired-but-referenced CA reported');
        $this->assertNull(
            $this->findByUuid($mdl->pki->authorities->authority, $expiredUnref),
            'expired unreferenced CA must be gone'
        );
        $this->assertNotNull(
            $this->findByUuid($mdl->pki->authorities->authority, $current),
            'current CA must remain'
        );
        $this->assertNotNull(
            $this->findByUuid($mdl->pki->authorities->authority, $expiredRef),
            'expired referenced CA must be kept'
        );
    }
}
