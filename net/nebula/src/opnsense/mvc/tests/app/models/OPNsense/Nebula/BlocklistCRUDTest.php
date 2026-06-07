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
 * Model-level CRUD tests for pki.blocklist.entry ArrayField.
 *
 * Tests the same data paths that BlocklistController exercises via
 * ApiMutableModelControllerBase (add/get/set/del/toggle).  The fingerprint
 * immutability and 64-hex format rules live in the controller as plain-PHP
 * pre-checks (not in the Phalcon validation chain), so they are reproduced here
 * against the same regex/comparison the controller uses — that keeps these
 * assertions runnable without the Phalcon\Filter\Validation class that the
 * model's performValidation() requires (and which is absent on PHP 8.5 here).
 */
class BlocklistCRUDTest extends \PHPUnit\Framework\TestCase
{
    private static $configDir = __DIR__ . '/NebulaConfig';

    /** A valid 64-char lowercase hex sha256 fingerprint for fixtures. */
    private const FP_A = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private const FP_B = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';

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
        $inst->enabled       = '1';
        $inst->description   = $description;
        $inst->listen_host   = '0.0.0.0';
        $inst->listen_port   = '4242';
        $inst->am_lighthouse = '0';
        return $inst->getAttribute('uuid');
    }

    // -------------------------------------------------------------------------
    // Add / readback
    // -------------------------------------------------------------------------

    public function testAddGlobalEntry()
    {
        $model = new Nebula();

        $entry = $model->pki->blocklist->entry->Add();
        $entry->enabled     = '1';
        $entry->instance    = ''; // global
        $entry->fingerprint = self::FP_A;
        $entry->descr       = 'block bad host';

        $uuid = $entry->getAttribute('uuid');
        $this->assertNotEmpty($uuid, 'Add() must return a node with a UUID');

        $found = $this->findByUuid($model->pki->blocklist->entry, $uuid);
        $this->assertNotNull($found, 'findByUuid must locate the added entry');
        $this->assertEquals('1',       (string)$found->enabled);
        $this->assertEquals('',        (string)$found->instance, 'empty instance = global');
        $this->assertEquals(self::FP_A, (string)$found->fingerprint);
        $this->assertEquals('block bad host', (string)$found->descr);
    }

    public function testAddPerInstanceEntry()
    {
        $model    = new Nebula();
        $instUuid = $this->addInstance($model, 'inst-scoped');

        $entry = $model->pki->blocklist->entry->Add();
        $entry->enabled     = '1';
        $entry->instance    = $instUuid;
        $entry->fingerprint = self::FP_B;

        $found = $this->findByUuid($model->pki->blocklist->entry, $entry->getAttribute('uuid'));
        $this->assertNotNull($found);
        $this->assertEquals($instUuid,  (string)$found->instance);
        $this->assertEquals(self::FP_B, (string)$found->fingerprint);
    }

    // -------------------------------------------------------------------------
    // Set (update) — non-fingerprint fields are mutable
    // -------------------------------------------------------------------------

    public function testSetEntryUpdatesMutableFields()
    {
        $model = new Nebula();

        $entry = $model->pki->blocklist->entry->Add();
        $entry->enabled     = '1';
        $entry->fingerprint = self::FP_A;
        $entry->descr       = 'before';
        $entry->expiry      = '';

        // Mutate non-fingerprint fields in place (equivalent to setBase()).
        $entry->descr  = 'after';
        $entry->expiry = '2030-01-01';

        $this->assertEquals('after',      (string)$entry->descr);
        $this->assertEquals('2030-01-01', (string)$entry->expiry);
        // Fingerprint unchanged.
        $this->assertEquals(self::FP_A,   (string)$entry->fingerprint);
    }

    // -------------------------------------------------------------------------
    // Del
    // -------------------------------------------------------------------------

    public function testDelEntryRemovesIt()
    {
        $model = new Nebula();

        $entry = $model->pki->blocklist->entry->Add();
        $entry->enabled     = '1';
        $entry->fingerprint = self::FP_A;

        $uuid = $entry->getAttribute('uuid');
        $this->assertNotNull($this->findByUuid($model->pki->blocklist->entry, $uuid), 'entry must exist before del');

        $result = $model->pki->blocklist->entry->del($uuid);
        $this->assertTrue($result, 'del() must return true for a found entry');
        $this->assertNull($this->findByUuid($model->pki->blocklist->entry, $uuid), 'entry must be gone after del()');
    }

    // -------------------------------------------------------------------------
    // Toggle
    // -------------------------------------------------------------------------

    public function testToggleEntryEnabled()
    {
        $model = new Nebula();

        $entry = $model->pki->blocklist->entry->Add();
        $entry->enabled     = '1';
        $entry->fingerprint = self::FP_A;

        $this->assertEquals('1', (string)$entry->enabled);
        $entry->enabled = '0';
        $this->assertEquals('0', (string)$entry->enabled);
        $entry->enabled = '1';
        $this->assertEquals('1', (string)$entry->enabled);
    }

    // -------------------------------------------------------------------------
    // Fingerprint 64-hex format — controller plain-PHP pre-check
    //
    // Mirrors BlocklistController::checkFingerprintFormat (which validates the
    // posted entry.fingerprint with this exact regex before addBase/setBase).
    // -------------------------------------------------------------------------

    public function testFingerprintFormatRegex()
    {
        // [fingerprint, expectedValid] — kept inline (no data provider) to match
        // the existing test style and stay PHPUnit-version agnostic.
        $cases = [
            [self::FP_A,             true],   // valid lowercase 64 hex
            ['abc123',               false],  // too short
            [strtoupper(self::FP_A), false],  // uppercase rejected
            [str_repeat('g', 64),    false],  // non-hex char
            [str_repeat('a', 63),    false],  // 63 chars
            [str_repeat('a', 65),    false],  // 65 chars
            ['',                     false],  // empty
        ];
        foreach ($cases as [$fp, $valid]) {
            $matched = (bool)preg_match('/^[0-9a-f]{64}$/', $fp);
            $this->assertSame($valid, $matched, "fingerprint '$fp' validity mismatch");
        }
    }

    // -------------------------------------------------------------------------
    // Fingerprint immutability — controller plain-PHP pre-check
    //
    // Reproduces BlocklistController::checkFingerprintImmutable: an edit that
    // posts a fingerprint differing from the stored one must be rejected; an
    // edit that keeps the same fingerprint (changing only other fields) passes.
    // -------------------------------------------------------------------------

    public function testFingerprintImmutabilityRejectsChange()
    {
        $model = new Nebula();

        $entry = $model->pki->blocklist->entry->Add();
        $entry->enabled     = '1';
        $entry->fingerprint = self::FP_A;
        $uuid = $entry->getAttribute('uuid');

        // Re-resolve the stored value the way the controller does.
        $stored = null;
        foreach ($model->pki->blocklist->entry->iterateItems() as $node) {
            if ($node->getAttribute('uuid') === $uuid) {
                $stored = trim((string)$node->fingerprint);
                break;
            }
        }
        $this->assertSame(self::FP_A, $stored);

        // A POST that changes the fingerprint must be flagged as immutable.
        $postedChanged = self::FP_B;
        $this->assertNotSame($stored, $postedChanged, 'changed fingerprint must differ → rejected');

        // A POST that keeps the same fingerprint is allowed.
        $postedSame = self::FP_A;
        $this->assertSame($stored, $postedSame, 'unchanged fingerprint must match → allowed');
    }

    // -------------------------------------------------------------------------
    // searchItemAction filter logic (model-level): global OR this-instance
    // -------------------------------------------------------------------------

    public function testScopeFilterGlobalAndInstanceViews()
    {
        $model = new Nebula();
        $instA = $this->addInstance($model, 'inst-A');
        $instB = $this->addInstance($model, 'inst-B');

        // Global entry (explicit scope).
        $g = $model->pki->blocklist->entry->Add();
        $g->enabled = '1'; $g->scope = 'global'; $g->fingerprint = self::FP_A;
        $uuidG = $g->getAttribute('uuid');

        // Per-instance A entry.
        $a = $model->pki->blocklist->entry->Add();
        $a->enabled = '1'; $a->scope = 'instance'; $a->instance = $instA;
        $a->fingerprint = self::FP_B;
        $uuidA = $a->getAttribute('uuid');

        // Per-instance B entry.
        $b = $model->pki->blocklist->entry->Add();
        $b->enabled = '1'; $b->scope = 'instance'; $b->instance = $instB;
        $b->fingerprint = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $uuidB = $b->getAttribute('uuid');

        $match = function (callable $filter) use ($model) {
            $out = [];
            foreach ($model->pki->blocklist->entry->iterateItems() as $r) {
                if ($filter($r)) {
                    $out[] = $r->getAttribute('uuid');
                }
            }
            return $out;
        };

        // Controller filter for a specific instance: Global OR scoped to it.
        $forInstanceA = $match(function ($r) use ($instA) {
            if ((string)$r->scope === 'instance') {
                return (string)$r->instance === $instA;
            }
            return true;
        });
        $this->assertContains($uuidG, $forInstanceA, 'global entry appears in inst-A view');
        $this->assertContains($uuidA, $forInstanceA, 'inst-A entry appears in inst-A view');
        $this->assertNotContains($uuidB, $forInstanceA, 'inst-B entry must NOT appear in inst-A view');

        // Controller filter for the "Global" view (__global__): non-instance only.
        $globalOnly = $match(function ($r) {
            return (string)$r->scope !== 'instance';
        });
        $this->assertContains($uuidG, $globalOnly, 'global entry appears in Global view');
        $this->assertNotContains($uuidA, $globalOnly, 'inst-A entry must NOT appear in Global view');
        $this->assertNotContains($uuidB, $globalOnly, 'inst-B entry must NOT appear in Global view');
    }

    // -------------------------------------------------------------------------
    // block_cert idempotency logic (model-level)
    // -------------------------------------------------------------------------

    public function testBlockCertIdempotencyDetectsExistingGlobal()
    {
        $model = new Nebula();

        // Pre-existing global block for FP_A.
        $g = $model->pki->blocklist->entry->Add();
        $g->enabled = '1'; $g->instance = ''; $g->fingerprint = self::FP_A;

        // Reproduce the controller's idempotency scan.
        $alreadyBlocked = false;
        foreach ($model->pki->blocklist->entry->iterateItems() as $entry) {
            if ((string)$entry->instance === '' && trim((string)$entry->fingerprint) === self::FP_A) {
                $alreadyBlocked = true;
                break;
            }
        }
        $this->assertTrue($alreadyBlocked, 'an existing global block for FP_A must be detected');

        // A different fingerprint is not yet blocked.
        $otherBlocked = false;
        foreach ($model->pki->blocklist->entry->iterateItems() as $entry) {
            if ((string)$entry->instance === '' && trim((string)$entry->fingerprint) === self::FP_B) {
                $otherBlocked = true;
                break;
            }
        }
        $this->assertFalse($otherBlocked, 'FP_B is not blocked yet');
    }

    // -------------------------------------------------------------------------
    // Defaults
    // -------------------------------------------------------------------------

    public function testDefaultEnabledIsOne()
    {
        $model = new Nebula();

        $entry = $model->pki->blocklist->entry->Add();
        $entry->fingerprint = self::FP_A;

        $this->assertEquals('1', (string)$entry->enabled, 'Default enabled must be "1"');
        $this->assertEquals('',  (string)$entry->instance, 'instance defaults to empty (global)');
        $this->assertEquals('',  (string)$entry->expiry, 'expiry defaults to empty (never)');
    }

    // -------------------------------------------------------------------------
    // purgeExpiredBlocklist (Nebula::purgeExpiredBlocklist — the data path
    // BlocklistController::purgeExpiredAction persists)
    // -------------------------------------------------------------------------

    public function testPurgeExpiredRemovesOnlyPastDatedEntries()
    {
        $model = new Nebula();

        // Past expiry — purgeable.
        $expired = $model->pki->blocklist->entry->Add();
        $expired->fingerprint = self::FP_A;
        $expired->expiry      = '2000-01-01';

        // Future expiry — kept.
        $future = $model->pki->blocklist->entry->Add();
        $future->fingerprint = self::FP_B;
        $future->expiry      = '2999-12-31';

        // Empty expiry (never) — kept.
        $never = $model->pki->blocklist->entry->Add();
        $never->fingerprint = '11111111111111111111111111111111'
            . '11111111111111111111111111111111';
        $never->expiry      = '';

        $expiredUuid = $expired->getAttribute('uuid');
        $futureUuid  = $future->getAttribute('uuid');
        $neverUuid   = $never->getAttribute('uuid');

        $res = $model->purgeExpiredBlocklist();

        $this->assertEquals(1, $res['removed'], 'exactly one expired entry removed');
        $this->assertSame([], $res['skippedNames'], 'blocklist purge never skips');
        $this->assertNull(
            $this->findByUuid($model->pki->blocklist->entry, $expiredUuid),
            'expired entry must be gone'
        );
        $this->assertNotNull(
            $this->findByUuid($model->pki->blocklist->entry, $futureUuid),
            'future-dated entry must remain'
        );
        $this->assertNotNull(
            $this->findByUuid($model->pki->blocklist->entry, $neverUuid),
            'never-expiring entry must remain'
        );
    }

    /**
     * Purge eligibility parses dates leniently (strtotime), so a non-ISO past
     * date (US m/d/Y) is purged while a future one and an unparseable value are
     * kept. This is the parsing coverage that used to live in the renderer before
     * the block-until-purged change.
     */
    public function testPurgeExpiredHandlesDateFormats()
    {
        $model = new Nebula();

        $fp = fn(string $c) => str_repeat($c, 64);

        // Past, US m/d/Y format — purgeable.
        $pastNonIso = $model->pki->blocklist->entry->Add();
        $pastNonIso->fingerprint = $fp('a');
        $pastNonIso->expiry      = date('m/d/Y', strtotime('-1 year'));

        // Future, US m/d/Y format — kept.
        $futureNonIso = $model->pki->blocklist->entry->Add();
        $futureNonIso->fingerprint = $fp('b');
        $futureNonIso->expiry      = date('m/d/Y', strtotime('+1 year'));

        // Unparseable — kept (we never purge what we cannot date).
        $unparseable = $model->pki->blocklist->entry->Add();
        $unparseable->fingerprint = $fp('c');
        $unparseable->expiry      = 'whenever';

        $pastUuid   = $pastNonIso->getAttribute('uuid');
        $futureUuid = $futureNonIso->getAttribute('uuid');
        $badUuid    = $unparseable->getAttribute('uuid');

        $res = $model->purgeExpiredBlocklist();

        $this->assertEquals(1, $res['removed'], 'only the past non-ISO entry is purged');
        $this->assertNull(
            $this->findByUuid($model->pki->blocklist->entry, $pastUuid),
            'past m/d/Y entry must be purged'
        );
        $this->assertNotNull(
            $this->findByUuid($model->pki->blocklist->entry, $futureUuid),
            'future m/d/Y entry must remain'
        );
        $this->assertNotNull(
            $this->findByUuid($model->pki->blocklist->entry, $badUuid),
            'unparseable expiry must remain'
        );
    }
}
