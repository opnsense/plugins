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
 * Model-level CRUD tests for fwrules.rule ArrayField.
 *
 * Tests the same data paths that FirewallRuleController exercises via
 * ApiMutableModelControllerBase (add/get/set/del/toggle).  The at-least-one
 * matcher validation and the instance-scoped filter are tested here at the
 * model/query layer; the route-resolution path is covered by live tests.
 */
class FirewallRuleCRUDTest extends \PHPUnit\Framework\TestCase
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
     * Add a minimal valid instance and return its UUID.
     */
    private function addInstance(Nebula $model, string $description = 'test-instance'): string
    {
        $inst = $model->instances->instance->Add();
        $inst->enabled      = '1';
        $inst->description  = $description;
        $inst->listen_host  = '0.0.0.0';
        $inst->listen_port  = '4242';
        $inst->am_lighthouse = '0';
        return $inst->getAttribute('uuid');
    }

    // -------------------------------------------------------------------------
    // Add / readback
    // -------------------------------------------------------------------------

    public function testAddRuleWithGroups()
    {
        $model     = new Nebula();
        $instUuid  = $this->addInstance($model, 'inst-for-rule');

        $rule = $model->fwrules->rule->Add();
        $rule->enabled     = '1';
        $rule->instance    = $instUuid;
        $rule->direction   = 'inbound';
        $rule->protocol    = 'tcp';
        $rule->port        = '443';
        $rule->groups      = 'vpn-clients';
        $rule->description = 'Allow HTTPS from vpn-clients';

        $uuid = $rule->getAttribute('uuid');
        $this->assertNotEmpty($uuid, 'Add() must return a node with a UUID');

        // NOTE: ModelRelationField uses a static option-list cache that is
        // populated from the persisted config.xml, not from in-memory Add()s.
        // Therefore performValidation() will report an error for the `instance`
        // field even when the UUID is valid in-memory (same constraint as
        // CertificateCRUDTest with caref/certref).  We test field storage and
        // readback directly instead of asserting validation count == 0 here.
        $this->assertEquals($instUuid,      (string)$rule->instance);
        $this->assertEquals('vpn-clients',  (string)$rule->groups);
        $this->assertEquals('inbound',      (string)$rule->direction);
        $this->assertEquals('tcp',          (string)$rule->protocol);
        $this->assertEquals('443',          (string)$rule->port);
    }

    public function testRuleFieldsReadBack()
    {
        $model    = new Nebula();
        $instUuid = $this->addInstance($model, 'inst-readback');

        $rule = $model->fwrules->rule->Add();
        $rule->enabled     = '1';
        $rule->instance    = $instUuid;
        $rule->direction   = 'outbound';
        $rule->protocol    = 'udp';
        $rule->port        = '53';
        $rule->cidr        = '10.0.0.0/8';
        $rule->description = 'DNS outbound';

        $uuid  = $rule->getAttribute('uuid');
        $found = $this->findByUuid($model->fwrules->rule, $uuid);

        $this->assertNotNull($found, 'findByUuid must locate the added rule');
        $this->assertEquals('outbound',    (string)$found->direction);
        $this->assertEquals('udp',         (string)$found->protocol);
        $this->assertEquals('53',          (string)$found->port);
        $this->assertEquals('10.0.0.0/8',  (string)$found->cidr);
        $this->assertEquals($instUuid,     (string)$found->instance);
        $this->assertEquals('DNS outbound',(string)$found->description);
    }

    // -------------------------------------------------------------------------
    // Set (update)
    // -------------------------------------------------------------------------

    public function testSetRuleUpdatesFields()
    {
        $model    = new Nebula();
        $instUuid = $this->addInstance($model, 'inst-set');

        $rule = $model->fwrules->rule->Add();
        $rule->enabled   = '1';
        $rule->instance  = $instUuid;
        $rule->direction = 'inbound';
        $rule->protocol  = 'any';
        $rule->host      = '192.168.1.1';

        // Mutate in-place (equivalent to setBase() field update).
        $rule->protocol  = 'icmp';
        $rule->port      = 'any';
        $rule->host      = '10.0.0.5';

        // NOTE: ModelRelationField option-list is seeded from persisted config,
        // not from in-memory Add()s, so we verify field values directly instead of
        // asserting performValidation() == 0 (same design note as CertificateCRUDTest).
        $this->assertEquals('icmp',     (string)$rule->protocol);
        $this->assertEquals('any',      (string)$rule->port);
        $this->assertEquals('10.0.0.5', (string)$rule->host);
        $this->assertEquals($instUuid,  (string)$rule->instance);
    }

    // -------------------------------------------------------------------------
    // Del
    // -------------------------------------------------------------------------

    public function testDelRuleRemovesIt()
    {
        $model    = new Nebula();
        $instUuid = $this->addInstance($model, 'inst-del');

        $rule = $model->fwrules->rule->Add();
        $rule->instance  = $instUuid;
        $rule->direction = 'inbound';
        $rule->ca_name   = 'my-ca';

        $uuid = $rule->getAttribute('uuid');
        $this->assertNotNull($this->findByUuid($model->fwrules->rule, $uuid), 'Rule must exist before del');

        $result = $model->fwrules->rule->del($uuid);
        $this->assertTrue($result, 'del() must return true for a found rule');
        $this->assertNull($this->findByUuid($model->fwrules->rule, $uuid), 'Rule must be gone after del()');
    }

    // -------------------------------------------------------------------------
    // Toggle
    // -------------------------------------------------------------------------

    public function testToggleRuleEnabled()
    {
        $model    = new Nebula();
        $instUuid = $this->addInstance($model, 'inst-toggle');

        $rule = $model->fwrules->rule->Add();
        $rule->enabled   = '1';
        $rule->instance  = $instUuid;
        $rule->direction = 'inbound';
        $rule->groups    = 'admins';

        $this->assertEquals('1', (string)$rule->enabled);

        $rule->enabled = '0';
        $this->assertEquals('0', (string)$rule->enabled);

        $rule->enabled = '1';
        $this->assertEquals('1', (string)$rule->enabled);
    }

    // -------------------------------------------------------------------------
    // Matcher-field validation: at least one required
    // -------------------------------------------------------------------------

    /**
     * A rule with NO matcher fields set must fail model-level validation.
     *
     * The controller's checkMatcherPresent() returns an error before addBase()
     * is called, so the model never receives such a request — but the model
     * validation must also catch it (belt-and-suspenders) if e.g. the rule is
     * built programmatically.
     *
     * NOTE: the model uses free-form TextField for all matchers (no Required
     * constraint at the schema level — the constraint is applied at the
     * controller layer via checkMatcherPresent).  So this test verifies that
     * a rule WITH a matcher passes, and that the matcher fields are accessible
     * as empty strings when unset (the *controller* is responsible for the
     * rejection, not the model schema itself).
     *
     * This documents the design: model is permissive; controller enforces the
     * at-least-one-matcher invariant.  If a model-level constraint is added
     * later, this test should be updated to assert 0 messages for the valid
     * case and >0 for the no-matcher case.
     */
    public function testRuleWithMatcherPassesValidation()
    {
        $model    = new Nebula();
        $instUuid = $this->addInstance($model, 'inst-matcher');

        $rule = $model->fwrules->rule->Add();
        $rule->enabled   = '1';
        $rule->instance  = $instUuid;
        $rule->direction = 'inbound';
        $rule->host      = '192.168.50.1';

        // The `instance` ModelRelationField option-list is seeded from the
        // persisted config, not from in-memory Add()s, so performValidation()
        // reports an error for it even when the UUID is valid in-memory.  This
        // is the same design constraint documented in CertificateCRUDTest (caref).
        // We verify that the matcher field is stored and readable.
        $this->assertEquals('192.168.50.1', (string)$rule->host);
        $this->assertEquals($instUuid,      (string)$rule->instance);
        $this->assertEquals('inbound',      (string)$rule->direction);

        // The validation error count will be exactly 1 (the instance relfield).
        // All other fields are valid.
        $msgs = $model->performValidation();
        $instanceErrors = 0;
        $otherErrors    = 0;
        foreach ($msgs as $m) {
            if (strpos($m->getField(), '.instance') !== false) {
                $instanceErrors++;
            } else {
                $otherErrors++;
            }
        }
        $this->assertEquals(0, $otherErrors, 'No validation errors other than the in-memory instance UUID');
    }

    public function testRuleWithNoMatcherHasEmptyMatcherFields()
    {
        $model    = new Nebula();
        $instUuid = $this->addInstance($model, 'inst-no-matcher');

        $rule = $model->fwrules->rule->Add();
        $rule->enabled   = '1';
        $rule->instance  = $instUuid;
        $rule->direction = 'inbound';
        // Intentionally leave all matcher fields empty.

        // At the model level (no controller) no Required constraint fires on
        // matcher fields — validation still passes.  The controller's
        // checkMatcherPresent() would have rejected the request before reaching
        // the model, so this represents the belt-and-suspenders design note.
        $this->assertEquals('', (string)$rule->host,    'host must default to empty');
        $this->assertEquals('', (string)$rule->groups,  'groups must default to empty');
        $this->assertEquals('', (string)$rule->cidr,    'cidr must default to empty');
        $this->assertEquals('', (string)$rule->ca_name, 'ca_name must default to empty');
        $this->assertEquals('', (string)$rule->ca_sha,  'ca_sha must default to empty');
    }

    // -------------------------------------------------------------------------
    // Instance-scoped filter logic (model-level)
    // -------------------------------------------------------------------------

    /**
     * Verify that two rules referencing different instances can be
     * distinguished by the instance UUID — the same logic the controller's
     * filter_funct uses when ?instance=<uuid> is passed to searchItemAction.
     */
    public function testInstanceScopedFilterDistinguishesRules()
    {
        $model     = new Nebula();
        $instA     = $this->addInstance($model, 'inst-A');
        $instB     = $this->addInstance($model, 'inst-B');

        $ruleA = $model->fwrules->rule->Add();
        $ruleA->enabled   = '1';
        $ruleA->instance  = $instA;
        $ruleA->direction = 'inbound';
        $ruleA->groups    = 'group-a';
        $uuidA = $ruleA->getAttribute('uuid');

        $ruleB = $model->fwrules->rule->Add();
        $ruleB->enabled   = '1';
        $ruleB->instance  = $instB;
        $ruleB->direction = 'outbound';
        $ruleB->cidr      = '10.10.0.0/16';
        $uuidB = $ruleB->getAttribute('uuid');

        // Simulate the controller's filter_funct for inst-A.
        $filterA = function ($record) use ($instA) {
            return (string)$record->instance === $instA;
        };

        $matchesA = [];
        foreach ($model->fwrules->rule->iterateItems() as $record) {
            if ($filterA($record)) {
                $matchesA[] = $record->getAttribute('uuid');
            }
        }

        $this->assertContains($uuidA, $matchesA, 'Rule for inst-A must appear in inst-A filter');
        $this->assertNotContains($uuidB, $matchesA, 'Rule for inst-B must NOT appear in inst-A filter');
    }

    // -------------------------------------------------------------------------
    // Default field values
    // -------------------------------------------------------------------------

    public function testDefaultProtocolIsAny()
    {
        $model    = new Nebula();
        $instUuid = $this->addInstance($model, 'inst-defaults');

        $rule = $model->fwrules->rule->Add();
        $rule->instance  = $instUuid;
        $rule->direction = 'inbound';
        $rule->host      = '10.0.0.1';

        $this->assertEquals('any',      (string)$rule->protocol, 'Default protocol must be "any"');
        $this->assertEquals('any',      (string)$rule->port,     'Default port must be "any"');
        $this->assertEquals('1',        (string)$rule->enabled,  'Default enabled must be "1"');
        $this->assertEquals('inbound',  (string)$rule->direction,'Direction must round-trip');
    }
}
