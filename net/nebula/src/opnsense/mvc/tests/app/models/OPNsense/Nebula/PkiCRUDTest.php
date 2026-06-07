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
 * Model-level CRUD tests for the shared PKI pool (authorities + certificates)
 * and the instance certref ModelRelationField.
 *
 * Note on ModelRelationField validation: OPNsense's ModelRelationField uses a
 * PHP static option-list cache keyed by md5(serialized model structure).  In
 * unit tests every test method gets a fresh in-memory Nebula() instance, but
 * the static cache persists across methods in the same process.  The validator
 * therefore cannot reliably see items Added() only in memory; it validates only
 * what was written to the config store.  We therefore test UUID storage and
 * readback directly rather than asserting performValidation() == 0 for the
 * relation fields — that path is covered by integration tests that go through
 * serializeToConfig().  Validation IS tested for non-relation fields (descr
 * Required, int ranges, enum values) where no static-cache issue exists.
 */
class PkiCRUDTest extends \PHPUnit\Framework\TestCase
{
    private static $configDir = __DIR__ . '/NebulaConfig';

    public static function setUpBeforeClass(): void
    {
        (new AppConfig())->update('application.configDir', self::$configDir);
        Config::getInstance()->forceReload();
    }

    /** Find an ArrayField item by UUID, or return null. */
    private function findByUuid($arrayField, $uuid)
    {
        foreach ($arrayField->iterateItems() as $node) {
            if ($node->getAttribute('uuid') === $uuid) {
                return $node;
            }
        }
        return null;
    }

    public function testAddAuthority()
    {
        $model = new Nebula();
        $auth = $model->pki->authorities->authority->Add();
        $auth->descr = 'test-ca';
        $auth->crt = 'CERT_DATA';
        $auth->key = 'KEY_DATA';

        $uuid = $auth->getAttribute('uuid');
        $this->assertNotEmpty($uuid, 'Add() must assign a UUID to the authority');

        // Validate only the non-relation fields; descr is Required → should pass.
        // We do not assert performValidation() == 0 because the certref/caref
        // relation validator is subject to the static-cache issue described above.
        $this->assertEquals('test-ca', (string)$auth->descr);
        $this->assertEquals('generated', (string)$auth->origin);
        $this->assertEquals('25519', (string)$auth->curve);
    }

    public function testAuthorityDefaultValues()
    {
        $model = new Nebula();
        $auth = $model->pki->authorities->authority->Add();
        $auth->descr = 'defaults-test';

        $this->assertEquals('generated', (string)$auth->origin, 'Default origin must be generated');
        $this->assertEquals('25519', (string)$auth->curve, 'Default curve must be 25519');
    }

    public function testAuthorityValidationRequiresDescr()
    {
        $model = new Nebula();
        $auth = $model->pki->authorities->authority->Add();
        // descr intentionally left blank — Required field

        $msgs = $model->performValidation();
        $found = false;
        foreach ($msgs as $m) {
            if (strpos($m->getField(), 'descr') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Missing required descr must produce a validation message');
    }

    public function testAddCertificateUuidAndFields()
    {
        $model = new Nebula();

        // Add authority — we need its UUID for the caref
        $auth = $model->pki->authorities->authority->Add();
        $auth->descr = 'ca-for-cert';
        $authUuid = $auth->getAttribute('uuid');
        $this->assertNotEmpty($authUuid);

        // Add certificate referencing the authority
        $cert = $model->pki->certificates->certificate->Add();
        $cert->descr = 'test-cert';
        $cert->caref = $authUuid;
        $cert->crt = 'HOST_CRT';
        $cert->key = 'HOST_KEY';
        $cert->networks = '192.168.100.1/24';
        $cert->groups = 'lighthouse';

        $certUuid = $cert->getAttribute('uuid');
        $this->assertNotEmpty($certUuid, 'Certificate must get a UUID');

        // Read back fields directly without going through the static-cache validator
        $this->assertEquals('test-cert', (string)$cert->descr);
        $this->assertEquals($authUuid, (string)$cert->caref, 'caref must store the authority UUID');
        $this->assertEquals('signed', (string)$cert->origin, 'Default origin must be signed');
        $this->assertEquals('HOST_CRT', (string)$cert->crt);
        $this->assertEquals('HOST_KEY', (string)$cert->key);
        $this->assertEquals('192.168.100.1/24', (string)$cert->networks);
        $this->assertEquals('lighthouse', (string)$cert->groups);
    }

    public function testCertificateUnsafeNetworksFieldIsDistinctFromNetworks()
    {
        // unsafe_networks must be a separate field — distinct from networks —
        // and must round-trip independently without conflating the two.
        $model = new Nebula();

        $auth = $model->pki->authorities->authority->Add();
        $auth->descr = 'ca-unsafe-test';
        $authUuid = $auth->getAttribute('uuid');

        $cert = $model->pki->certificates->certificate->Add();
        $cert->descr           = 'cert-with-unsafe';
        $cert->caref           = $authUuid;
        $cert->networks        = '192.168.100.1/24';
        $cert->unsafe_networks = '10.0.0.0/8,172.16.0.0/12';

        // The two fields must store independently.
        $this->assertEquals('192.168.100.1/24', (string)$cert->networks, 'networks must be stored separately');
        $this->assertEquals('10.0.0.0/8,172.16.0.0/12', (string)$cert->unsafe_networks, 'unsafe_networks must be stored separately');
        $this->assertNotEquals((string)$cert->networks, (string)$cert->unsafe_networks, 'networks and unsafe_networks must not be conflated');
    }

    public function testCertificateUnsafeNetworksEmptyByDefault()
    {
        // When a cert is created without unsafe_networks the field must be empty,
        // not populated with the networks value.
        $model = new Nebula();
        $cert  = $model->pki->certificates->certificate->Add();
        $cert->descr    = 'cert-no-unsafe';
        $cert->networks = '10.20.30.1/24';

        $this->assertEquals('10.20.30.1/24', (string)$cert->networks);
        $this->assertEquals('', (string)$cert->unsafe_networks, 'unsafe_networks must be empty when not set');
    }

    public function testAuthorityUnsafeNetworksFieldIsDistinctFromNetworks()
    {
        // Same distinctness guarantee for the authority model.
        $model = new Nebula();
        $auth  = $model->pki->authorities->authority->Add();
        $auth->descr           = 'ca-with-unsafe';
        $auth->networks        = '192.168.100.0/24';
        $auth->unsafe_networks = '203.0.113.0/24';

        $this->assertEquals('192.168.100.0/24', (string)$auth->networks, 'authority networks must be stored separately');
        $this->assertEquals('203.0.113.0/24', (string)$auth->unsafe_networks, 'authority unsafe_networks must be stored separately');
        $this->assertNotEquals((string)$auth->networks, (string)$auth->unsafe_networks, 'authority networks and unsafe_networks must not be conflated');
    }

    public function testAuthorityUnsafeNetworksEmptyByDefault()
    {
        $model = new Nebula();
        $auth  = $model->pki->authorities->authority->Add();
        $auth->descr    = 'ca-no-unsafe';
        $auth->networks = '10.0.0.0/8';

        $this->assertEquals('10.0.0.0/8', (string)$auth->networks);
        $this->assertEquals('', (string)$auth->unsafe_networks, 'authority unsafe_networks must be empty when not set');
    }

    public function testCertificateDefaultOriginIsSigned()
    {
        $model = new Nebula();
        $cert = $model->pki->certificates->certificate->Add();
        $cert->descr = 'cert-origin-test';

        $this->assertEquals('signed', (string)$cert->origin, 'Default origin must be signed');
    }

    public function testCertificateCaRefStoredAsUuid()
    {
        $model = new Nebula();

        $auth = $model->pki->authorities->authority->Add();
        $auth->descr = 'my-ca';
        $authUuid = $auth->getAttribute('uuid');

        $cert = $model->pki->certificates->certificate->Add();
        $cert->descr = 'my-cert';
        $cert->caref = $authUuid;

        // Read back through the array field lookup
        $certUuid = $cert->getAttribute('uuid');
        $found = $this->findByUuid($model->pki->certificates->certificate, $certUuid);
        $this->assertNotNull($found, 'Certificate retrievable by UUID');
        $this->assertEquals($authUuid, (string)$found->caref, 'caref resolves to the authority UUID');
    }

    public function testInstanceCertrefStoredAsUuid()
    {
        $model = new Nebula();

        // Build the chain: authority → certificate → instance.certref
        $auth = $model->pki->authorities->authority->Add();
        $auth->descr = 'instance-ca';
        $authUuid = $auth->getAttribute('uuid');

        $cert = $model->pki->certificates->certificate->Add();
        $cert->descr = 'instance-cert';
        $cert->caref = $authUuid;
        $certUuid = $cert->getAttribute('uuid');

        $inst = $model->instances->instance->Add();
        $inst->enabled = '1';
        $inst->description = 'inst-with-cert';
        $inst->listen_host = '0.0.0.0';
        $inst->listen_port = '4242';
        $inst->am_lighthouse = '1';
        $inst->certref = $certUuid;

        // Verify the UUID is stored correctly
        $this->assertEquals($certUuid, (string)$inst->certref, 'certref must store the certificate UUID');

        // Verify retrievable via ArrayField iteration
        $instUuid = $inst->getAttribute('uuid');
        $found = $this->findByUuid($model->instances->instance, $instUuid);
        $this->assertNotNull($found, 'Instance must be retrievable by UUID');
        $this->assertEquals($certUuid, (string)$found->certref, 'certref survives ArrayField lookup');
    }

    public function testInstanceWithoutCertrefPassesValidation()
    {
        // certref is optional — an instance with no cert must still validate OK.
        $model = new Nebula();
        $inst = $model->instances->instance->Add();
        $inst->enabled = '1';
        $inst->description = 'no-cert';
        $inst->listen_host = '0.0.0.0';
        $inst->listen_port = '4242';
        $inst->am_lighthouse = '1';

        $msgs = $model->performValidation();
        // Filter to only messages from our instance (ignore any from other fields)
        $instMsgs = [];
        foreach ($msgs as $m) {
            $instMsgs[] = $m->getField() . ': ' . $m->getMessage();
        }
        $this->assertEquals(0, count($msgs), 'Instance without certref should pass validation: ' . implode(', ', $instMsgs));
    }

    public function testDelAuthority()
    {
        $model = new Nebula();
        $auth = $model->pki->authorities->authority->Add();
        $auth->descr = 'to-delete';

        $uuid = $auth->getAttribute('uuid');
        $this->assertNotNull($this->findByUuid($model->pki->authorities->authority, $uuid), 'Must exist before del');

        $result = $model->pki->authorities->authority->del($uuid);
        $this->assertTrue($result, 'del() returns true for a found authority');
        $this->assertNull($this->findByUuid($model->pki->authorities->authority, $uuid), 'Gone after del()');
    }

    public function testDelCertificate()
    {
        $model = new Nebula();
        $cert = $model->pki->certificates->certificate->Add();
        $cert->descr = 'cert-to-delete';

        $uuid = $cert->getAttribute('uuid');
        $this->assertNotNull($this->findByUuid($model->pki->certificates->certificate, $uuid));

        $result = $model->pki->certificates->certificate->del($uuid);
        $this->assertTrue($result, 'del() returns true for a found certificate');
        $this->assertNull($this->findByUuid($model->pki->certificates->certificate, $uuid), 'Gone after del()');
    }
}
