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
 * Model-level CRUD tests for instances.instance ArrayField.
 * These exercise the same data paths the API controllers exercise via
 * ApiMutableModelControllerBase (add/get/set/del/toggle).
 */
class InstanceCRUDTest extends \PHPUnit\Framework\TestCase
{
    private static $configDir = __DIR__ . '/NebulaConfig';

    public static function setUpBeforeClass(): void
    {
        (new AppConfig())->update('application.configDir', self::$configDir);
        Config::getInstance()->forceReload();
    }

    /** Locate an ArrayField child by UUID, returning null if not found. */
    private function findByUuid($arrayField, $uuid)
    {
        foreach ($arrayField->iterateItems() as $node) {
            if ($node->getAttribute('uuid') === $uuid) {
                return $node;
            }
        }
        return null;
    }

    /**
     * The trusted_cas multi-relation round-trips a set of CA uuids (the
     * per-instance trusted-CA allow-list). Storage only — no validation — so it
     * runs without the Phalcon validation extension.
     */
    public function testTrustedCasStoresMultiple()
    {
        $model = new Nebula();
        $ca1 = $model->pki->authorities->authority->Add();
        $ca1->descr = 'ca-one';
        $ca2 = $model->pki->authorities->authority->Add();
        $ca2->descr = 'ca-two';
        $u1 = $ca1->getAttribute('uuid');
        $u2 = $ca2->getAttribute('uuid');

        $node = $model->instances->instance->Add();
        $node->description = 'multi-ca';
        $node->trusted_cas = $u1 . ',' . $u2;

        $parts = array_values(array_filter(explode(',', (string)$node->trusted_cas)));
        sort($parts);
        $expect = [$u1, $u2];
        sort($expect);
        $this->assertEquals($expect, $parts, 'trusted_cas must round-trip both CA uuids');
    }

    public function testAddInstance()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'test-lh';
        $node->listen_host = '0.0.0.0';
        $node->listen_port = '4242';
        $node->am_lighthouse = '1';

        $uuid = $node->getAttribute('uuid');
        $this->assertNotEmpty($uuid, 'Add() must return a node with a UUID');

        $msgs = $model->performValidation();
        $this->assertEquals(0, count($msgs), 'New valid instance should pass validation');
    }

    public function testGetInstance()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'get-test';
        $node->listen_host = '10.0.0.1';
        $node->listen_port = '4242';
        $node->am_lighthouse = '0';

        $uuid = $node->getAttribute('uuid');

        // Retrieve by iterating items (equivalent to getBase() lookup)
        $found = $this->findByUuid($model->instances->instance, $uuid);
        $this->assertNotNull($found, 'Should retrieve the added node by UUID');
        $this->assertEquals('get-test', (string)$found->description);
        $this->assertEquals('10.0.0.1', (string)$found->listen_host);
    }

    public function testSetInstance()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'before-set';
        $node->listen_host = '0.0.0.0';
        $node->listen_port = '4242';
        $node->am_lighthouse = '0';

        // Mutate the node in-place (equivalent to setBase() field update)
        $node->description = 'after-set';
        $node->listen_port = '7777';

        $msgs = $model->performValidation();
        $this->assertEquals(0, count($msgs), 'Mutated instance should still pass validation');
        $this->assertEquals('after-set', (string)$node->description);
        $this->assertEquals('7777', (string)$node->listen_port);
    }

    public function testDelInstance()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'to-delete';
        $node->listen_host = '0.0.0.0';
        $node->listen_port = '4242';
        $node->am_lighthouse = '0';

        $uuid = $node->getAttribute('uuid');
        $this->assertNotNull($this->findByUuid($model->instances->instance, $uuid), 'Node should exist before del');

        // del() takes the UUID as the array key
        $result = $model->instances->instance->del($uuid);
        $this->assertTrue($result, 'del() should return true for a found item');
        $this->assertNull($this->findByUuid($model->instances->instance, $uuid), 'Node should be gone after del()');
    }

    public function testToggleInstance()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'toggle-test';
        $node->listen_host = '0.0.0.0';
        $node->listen_port = '4242';
        $node->am_lighthouse = '0';

        $this->assertEquals('1', (string)$node->enabled);

        $node->enabled = '0';
        $this->assertEquals('0', (string)$node->enabled);

        $node->enabled = '1';
        $this->assertEquals('1', (string)$node->enabled);
    }

    public function testValidationRejectsInvalidPort()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'bad-port';
        $node->listen_host = '0.0.0.0';
        $node->listen_port = 'notaport';
        $node->am_lighthouse = '0';

        $msgs = $model->performValidation();
        $this->assertGreaterThan(0, count($msgs), 'Invalid port should fail validation');
    }

    // -------------------------------------------------------------------------
    // assignDeviceNames() — stable, never-reused tun device naming (#35)
    // -------------------------------------------------------------------------

    private function addInst($model, string $descr): string
    {
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = $descr;
        $node->listen_host = '::';
        return $node->getAttribute('uuid');
    }

    public function testAssignDeviceNamesDerivesFromUuid()
    {
        $model = new Nebula();
        $ua = $this->addInst($model, 'alpha');
        $model->assignDeviceNames();
        $node = $this->findByUuid($model->instances->instance, $ua);
        $this->assertEquals('nebula' . substr(md5($ua), 0, 6), (string)$node->tun_name);
    }

    public function testAssignDeviceNamesKeepsExplicitName()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'custom';
        $node->listen_host = '::';
        $node->tun_name = 'mydev';
        $model->assignDeviceNames();
        $this->assertEquals('mydev', (string)$node->tun_name, 'an explicit name is never overwritten');
    }

    public function testAssignDeviceNamesStableAcrossDelete()
    {
        $model = new Nebula();
        $ua = $this->addInst($model, 'alpha');
        $ub = $this->addInst($model, 'beta');
        $model->assignDeviceNames();
        $nameA = (string)$this->findByUuid($model->instances->instance, $ua)->tun_name;

        // Deleting beta must NOT renumber alpha's device name.
        $model->instances->instance->del($ub);
        $model->assignDeviceNames();
        $this->assertEquals(
            $nameA,
            (string)$this->findByUuid($model->instances->instance, $ua)->tun_name,
            'deleting another instance must not change a stable device name'
        );
    }
}
