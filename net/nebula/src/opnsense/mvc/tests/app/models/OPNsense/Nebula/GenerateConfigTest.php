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

class GenerateConfigTest extends \PHPUnit\Framework\TestCase
{
    private static $configDir = __DIR__ . '/NebulaConfig';

    public static function setUpBeforeClass(): void
    {
        (new AppConfig())->update('application.configDir', self::$configDir);
        Config::getInstance()->forceReload();
    }

    /**
     * Add a minimal valid instance and return [model, node, uuid].
     */
    private function makeInstance(string $description = 'test'): array
    {
        $model = new Nebula();
        $node  = $model->instances->instance->Add();
        $node->enabled       = '1';
        $node->description   = $description;
        $node->listen_host   = '0.0.0.0';
        $node->listen_port   = '4242';
        $node->am_lighthouse = '0';
        return [$model, $node, $node->getAttribute('uuid')];
    }

    /**
     * Add an fwrule to $model and return the rule node.
     */
    private function addRule(Nebula $model, string $instUuid, string $direction, array $fields = [])
    {
        $rule = $model->fwrules->rule->Add();
        $rule->enabled   = '1';
        $rule->instance  = $instUuid;
        $rule->direction = $direction;
        foreach ($fields as $k => $v) {
            $rule->$k = $v;
        }
        return $rule;
    }

    // -------------------------------------------------------------------------
    // Firewall rule rendering tests (Task 2)
    // -------------------------------------------------------------------------

    /**
     * Two inbound rules + one outbound rule → rendered correctly per direction.
     */
    public function testFwrulesRenderPerDirection()
    {
        [$model, $node, $uuid] = $this->makeInstance('fw-per-dir');

        // Inbound rule 1: groups (single) + port + proto
        $this->addRule($model, $uuid, 'inbound', [
            'groups'   => 'db',
            'port'     => '5432',
            'protocol' => 'tcp',
        ]);
        // Inbound rule 2: host=any, port=any, proto=icmp
        $this->addRule($model, $uuid, 'inbound', [
            'host'     => 'any',
            'port'     => 'any',
            'protocol' => 'icmp',
        ]);
        // Outbound rule 1: a distinct real rule (groups=web, port 8080, udp).
        $this->addRule($model, $uuid, 'outbound', [
            'groups'   => 'web',
            'port'     => '8080',
            'protocol' => 'udp',
        ]);

        $yaml = $model->generateConfig($node);

        // Both inbound rules must appear.
        // groups renders as a YAML list even for a single entry.
        $this->assertStringContainsString('- "db"', $yaml);
        $this->assertStringContainsString('port: "5432"', $yaml);
        $this->assertStringContainsString('proto: "tcp"', $yaml);
        $this->assertStringContainsString('proto: "icmp"', $yaml);

        // The distinct outbound rule must appear (proves the permissive
        // fallback did NOT stomp real rules in the outbound direction).
        $this->assertStringContainsString('outbound:', $yaml);
        $this->assertStringContainsString('- "web"', $yaml);
        $this->assertStringContainsString('port: "8080"', $yaml);
        $this->assertStringContainsString('proto: "udp"', $yaml);
    }

    /**
     * An instance with NO enabled rules gets the permissive fallback on both directions.
     */
    public function testNoRulesFallbackToPermissive()
    {
        [$model, $node, $uuid] = $this->makeInstance('fw-no-rules');

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('firewall:', $yaml);
        $this->assertStringContainsString('inbound:', $yaml);
        $this->assertStringContainsString('outbound:', $yaml);
        $this->assertStringContainsString('port: "any"', $yaml);
        $this->assertStringContainsString('proto: "any"', $yaml);
        $this->assertStringContainsString('host: "any"', $yaml);
    }

    /**
     * An instance with only inbound rules gets the permissive fallback for outbound.
     */
    public function testOnlyInboundRulesGivesPermissiveFallbackForOutbound()
    {
        [$model, $node, $uuid] = $this->makeInstance('fw-inbound-only');

        $this->addRule($model, $uuid, 'inbound', [
            'host'     => '10.0.0.1',
            'port'     => '22',
            'protocol' => 'tcp',
        ]);

        $yaml = $model->generateConfig($node);

        // Inbound rule rendered.
        $this->assertStringContainsString('host: "10.0.0.1"', $yaml);
        // Outbound gets permissive fallback — check that 'proto: "any"' appears
        // (from the fallback, since the inbound rule has proto tcp, not any).
        $this->assertStringContainsString('proto: "any"', $yaml);
    }

    /**
     * A multi-value `groups` matcher is rendered as a YAML list.
     */
    public function testGroupsMatcherRendersAsList()
    {
        [$model, $node, $uuid] = $this->makeInstance('fw-groups-list');

        $this->addRule($model, $uuid, 'inbound', [
            'groups'   => 'admins,ops,dev',
            'port'     => '443',
            'protocol' => 'tcp',
        ]);

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('groups:', $yaml);
        $this->assertStringContainsString('- "admins"', $yaml);
        $this->assertStringContainsString('- "ops"', $yaml);
        $this->assertStringContainsString('- "dev"', $yaml);
    }

    /**
     * A single-entry `groups` value renders as a one-element YAML list.
     * (group/groups collapse: a single group is now groups:["a"].)
     */
    public function testSingleGroupRendersAsOneElementList()
    {
        [$model, $node, $uuid] = $this->makeInstance('fw-single-group');

        $this->addRule($model, $uuid, 'inbound', [
            'groups'   => 'mygroup',
            'port'     => '22',
            'protocol' => 'tcp',
        ]);

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('groups:', $yaml);
        $this->assertStringContainsString('- "mygroup"', $yaml);
        // Verify there is no bare scalar `group:` key emitted.
        $this->assertStringNotContainsString('group: "mygroup"', $yaml);
    }

    /**
     * Disabled rules are skipped; if all rules for an instance are disabled the
     * permissive fallback is returned for that direction.
     */
    public function testDisabledRulesAreSkipped()
    {
        [$model, $node, $uuid] = $this->makeInstance('fw-disabled');

        // Add a disabled inbound rule.
        $rule = $model->fwrules->rule->Add();
        $rule->enabled   = '0';
        $rule->instance  = $uuid;
        $rule->direction = 'inbound';
        $rule->host      = '10.99.0.1';

        // Add an enabled outbound rule.
        $this->addRule($model, $uuid, 'outbound', ['host' => '10.1.2.3']);

        $yaml = $model->generateConfig($node);

        // The disabled rule's host must not appear.
        $this->assertStringNotContainsString('10.99.0.1', $yaml);
        // Inbound falls back to permissive (the only inbound rule was disabled).
        $this->assertStringContainsString('proto: "any"', $yaml);
        // Outbound has the real rule.
        $this->assertStringContainsString('host: "10.1.2.3"', $yaml);
    }

    /**
     * Rules for a DIFFERENT instance must not appear in this instance's config.
     */
    public function testRulesFromOtherInstanceAreIgnored()
    {
        $model = new Nebula();

        $nodeA = $model->instances->instance->Add();
        $nodeA->enabled = '1'; $nodeA->description = 'inst-A';
        $nodeA->listen_host = '0.0.0.0'; $nodeA->listen_port = '4242';
        $nodeA->am_lighthouse = '0';
        $uuidA = $nodeA->getAttribute('uuid');

        $nodeB = $model->instances->instance->Add();
        $nodeB->enabled = '1'; $nodeB->description = 'inst-B';
        $nodeB->listen_host = '0.0.0.0'; $nodeB->listen_port = '4243';
        $nodeB->am_lighthouse = '0';
        $uuidB = $nodeB->getAttribute('uuid');

        // Rule for B only.
        $this->addRule($model, $uuidB, 'inbound', ['host' => '192.168.50.50']);

        $yaml = $model->generateConfig($nodeA);

        // Instance A has no rules → permissive fallback; B's host must not appear.
        $this->assertStringNotContainsString('192.168.50.50', $yaml);
        $this->assertStringContainsString('proto: "any"', $yaml);
    }

    public function testEnabledInstanceRendersMinimalYaml()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'lh';
        $node->listen_host = '0.0.0.0';
        $node->listen_port = '4242';
        $node->am_lighthouse = '1';

        $uuid = $node->getAttribute('uuid');
        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('listen:', $yaml);
        // strings are double-quoted; the listen host/port live under listen:
        $this->assertStringContainsString('host: "0.0.0.0"', $yaml);
        $this->assertStringContainsString('port: 4242', $yaml);
        $this->assertStringContainsString('am_lighthouse: true', $yaml);
        $this->assertNotEmpty($uuid);

        // pki: section must reference the per-instance cert dir (keyed by uuid).
        // pki paths are string scalars, hence double-quoted.
        $this->assertStringContainsString('pki:', $yaml);
        $this->assertStringContainsString('ca: "/usr/local/etc/nebula/' . $uuid . '/ca.crt"', $yaml);
        $this->assertStringContainsString('cert: "/usr/local/etc/nebula/' . $uuid . '/host.crt"', $yaml);
        $this->assertStringContainsString('key: "/usr/local/etc/nebula/' . $uuid . '/host.key"', $yaml);

        // firewall: must be present (nebula is deny-by-default) and permissive
        $this->assertStringContainsString('firewall:', $yaml);
        $this->assertStringContainsString('outbound:', $yaml);
        $this->assertStringContainsString('inbound:', $yaml);
        $this->assertStringContainsString('port: "any"', $yaml);
        $this->assertStringContainsString('proto: "any"', $yaml);
        $this->assertStringContainsString('host: "any"', $yaml);
    }

    public function testAdvancedBlockIsAppendedToOutput()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'adv-test';
        $node->listen_host = '0.0.0.0';
        $node->listen_port = '4242';
        $node->am_lighthouse = '1';
        $node->advanced = "stats:\n  type: prometheus\n  listen: \"127.0.0.1:8080\"\n  path: \"/metrics\"";

        $yaml = $model->generateConfig($node);

        // The advanced block must appear in the output, after the generated section.
        $this->assertStringContainsString('stats:', $yaml);
        $this->assertStringContainsString('type: prometheus', $yaml);
        $this->assertStringContainsString('listen: "127.0.0.1:8080"', $yaml);
        $this->assertStringContainsString('path: "/metrics"', $yaml);

        // The advanced block must come AFTER the generated pki: section.
        $pkiPos = strpos($yaml, 'pki:');
        $statsPos = strpos($yaml, 'stats:');
        $this->assertNotFalse($pkiPos, 'pki: must be present');
        $this->assertNotFalse($statsPos, 'stats: must be present');
        $this->assertGreaterThan($pkiPos, $statsPos, 'advanced block must follow generated YAML');

        // Output must end with a newline.
        $this->assertStringEndsWith("\n", $yaml);
    }

    public function testEmptyAdvancedLeavesOutputUnchanged()
    {
        $model = new Nebula();

        $nodeWithout = $model->instances->instance->Add();
        $nodeWithout->enabled = '1';
        $nodeWithout->description = 'no-adv';
        $nodeWithout->listen_host = '0.0.0.0';
        $nodeWithout->listen_port = '4242';
        $nodeWithout->am_lighthouse = '1';

        $nodeWith = $model->instances->instance->Add();
        $nodeWith->enabled = '1';
        $nodeWith->description = 'no-adv';
        $nodeWith->listen_host = '0.0.0.0';
        $nodeWith->listen_port = '4242';
        $nodeWith->am_lighthouse = '1';
        $nodeWith->advanced = '';

        $yamlWithout = $model->generateConfig($nodeWithout);
        $yamlWith = $model->generateConfig($nodeWith);

        // Empty advanced must not alter the output (no extra trailing junk).
        // We compare after normalising the per-instance bits (uuid cert paths and
        // the uuid-derived sshd debug port), which legitimately differ between the
        // two instances.
        $normalise = function (string $y): string {
            $y = preg_replace('#/usr/local/etc/nebula/[0-9a-f\-]+/#', '/UUID/', $y);
            $y = preg_replace('#127\.0\.0\.1:\d+#', '127.0.0.1:PORT', $y);
            return $y;
        };
        $this->assertSame($normalise($yamlWithout), $normalise($yamlWith));

        // No spurious blank line or separator at end of output.
        $this->assertStringNotContainsString("\n\n\n", $yaml = $yamlWith, 'no triple-newlines from empty advanced');
    }

    public function testListKnobNewlineSeparated()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'list-test';
        $node->listen_host = '0.0.0.0';
        $node->listen_port = '4242';
        $node->am_lighthouse = '0';
        $node->lighthouse_hosts = "192.168.100.1\n192.168.100.2";
        $node->relay_relays = '192.168.100.5';

        $yaml = $model->generateConfig($node);

        // lighthouse.hosts must render as a YAML sequence
        $this->assertStringContainsString("lighthouse:\n", $yaml);
        $this->assertStringContainsString("  hosts:\n", $yaml);
        $this->assertStringContainsString('    - "192.168.100.1"', $yaml);
        $this->assertStringContainsString('    - "192.168.100.2"', $yaml);

        // relay.relays must render as a YAML sequence
        $this->assertStringContainsString("relay:\n", $yaml);
        $this->assertStringContainsString("  relays:\n", $yaml);
        $this->assertStringContainsString('    - "192.168.100.5"', $yaml);
    }

    public function testListKnobCommaSeparated()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'list-comma';
        $node->listen_host = '0.0.0.0';
        $node->listen_port = '4242';
        $node->preferred_ranges = 'a, b, c';

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString("preferred_ranges:\n", $yaml);
        $this->assertStringContainsString('  - "a"', $yaml);
        $this->assertStringContainsString('  - "b"', $yaml);
        $this->assertStringContainsString('  - "c"', $yaml);
    }

    public function testListKnobEmptyIsOmitted()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'list-empty';
        $node->listen_host = '0.0.0.0';
        $node->listen_port = '4242';
        // lighthouse_advertise_addrs left empty (default)

        $yaml = $model->generateConfig($node);

        $this->assertStringNotContainsString('advertise_addrs', $yaml);
    }

    // -------------------------------------------------------------------------
    // static_hostmap grid rendering (+ legacy textarea union)
    // -------------------------------------------------------------------------

    /**
     * Grid entries render into static_host_map (map-of-lists) for their own
     * instance; addresses split on comma/newline.
     */
    public function testStaticHostMapGridRenders()
    {
        [$model, $node, $uuid] = $this->makeInstance('shm-grid');
        $node->lighthouse_hosts = '192.168.100.1';
        $e = $model->static_hostmap->entry->Add();
        $e->enabled   = '1';
        $e->instance  = $uuid;
        $e->nebula_ip = '192.168.100.1';
        $e->addresses = "198.51.100.1:4242, 203.0.113.5:4242";

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('static_host_map:', $yaml);
        $this->assertStringContainsString('192.168.100.1:', $yaml);
        $this->assertStringContainsString('- "198.51.100.1:4242"', $yaml);
        $this->assertStringContainsString('- "203.0.113.5:4242"', $yaml);

        // The static_host_map key must precede its list items.
        $shmPos = strpos($yaml, 'static_host_map:');
        $firstAddrPos = strpos($yaml, '"198.51.100.1:4242"');
        $this->assertNotFalse($shmPos);
        $this->assertNotFalse($firstAddrPos);
        $this->assertLessThan($firstAddrPos, $shmPos, 'static_host_map: must precede its list items');
    }

    /**
     * Grid entries for a different instance, and disabled ones, do not render.
     */
    public function testStaticHostMapGridScopedAndEnabled()
    {
        $model = new Nebula();
        $a = $model->instances->instance->Add();
        $a->enabled = '1'; $a->description = 'shm-A';
        $a->listen_host = '0.0.0.0'; $a->listen_port = '4242'; $a->am_lighthouse = '0';
        $uuidA = $a->getAttribute('uuid');
        $b = $model->instances->instance->Add();
        $b->enabled = '1'; $b->description = 'shm-B';
        $b->listen_host = '0.0.0.0'; $b->listen_port = '4243'; $b->am_lighthouse = '0';
        $uuidB = $b->getAttribute('uuid');

        $e = $model->static_hostmap->entry->Add();
        $e->enabled = '1'; $e->instance = $uuidB;
        $e->nebula_ip = '192.168.100.9'; $e->addresses = '198.51.100.9:4242';
        $d = $model->static_hostmap->entry->Add();
        $d->enabled = '0'; $d->instance = $uuidA;
        $d->nebula_ip = '192.168.100.8'; $d->addresses = '198.51.100.8:4242';

        $yamlA = $model->generateConfig($a);

        $this->assertStringNotContainsString('192.168.100.9', $yamlA, 'other-instance entry must not render');
        $this->assertStringNotContainsString('192.168.100.8', $yamlA, 'disabled entry must not render');
        $this->assertStringNotContainsString('static_host_map:', $yamlA);
    }

    /**
     * Addresses repeated across rows for the same overlay IP are de-duplicated.
     */
    public function testStaticHostMapDeduplicatesAddresses()
    {
        [$model, $node, $uuid] = $this->makeInstance('shm-dedup');
        $e1 = $model->static_hostmap->entry->Add();
        $e1->enabled = '1'; $e1->instance = $uuid;
        $e1->nebula_ip = '192.168.100.1';
        $e1->addresses = "198.51.100.1:4242, 203.0.113.5:4242";
        $e2 = $model->static_hostmap->entry->Add();
        $e2->enabled = '1'; $e2->instance = $uuid;
        $e2->nebula_ip = '192.168.100.1';
        $e2->addresses = "198.51.100.1:4242";

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('- "198.51.100.1:4242"', $yaml);
        $this->assertStringContainsString('- "203.0.113.5:4242"', $yaml);
        $this->assertSame(
            1,
            substr_count($yaml, '- "198.51.100.1:4242"'),
            'a repeated address must be de-duplicated'
        );
    }

    public function testDataDrivenRenderFromConfigMap()
    {
        $model = new Nebula();
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->description = 'data-driven';
        $node->listen_host = '::';
        $node->cipher = 'aes';
        $node->punchy_punch = '0';
        $node->listen_port = '0';
        $node->logging_level = 'debug';
        $node->tun_mtu = '1300';
        // leave listen_read_buffer empty (unset optional) -> must be omitted

        $uuid = $node->getAttribute('uuid');
        $yaml = $model->generateConfig($node);

        // header
        $this->assertStringContainsString('# Generated by os-nebula. Do not edit by hand.', $yaml);

        // enum/string scalars -> double quoted
        $this->assertStringContainsString('cipher: "aes"', $yaml);
        $this->assertStringContainsString('level: "debug"', $yaml);
        // the listen host "::" MUST be quoted, else YAML mis-parses the bare ::
        $this->assertStringContainsString('host: "::"', $yaml);

        // bool -> bare false (punch under punchy:)
        $this->assertStringContainsString('punch: false', $yaml);
        // bool -> bare true (sshd enabled)
        $this->assertStringContainsString('enabled: true', $yaml);

        // int -> bare, including port 0 (must emit 0, not omit)
        $this->assertStringContainsString('port: 0', $yaml);
        $this->assertStringContainsString('mtu: 1300', $yaml);

        // logging: is a real section header (level lives under it)
        $this->assertStringContainsString('logging:', $yaml);
        $this->assertStringContainsString('punchy:', $yaml);

        // pki: block with the uuid-derived paths
        $this->assertStringContainsString('pki:', $yaml);
        $this->assertStringContainsString('ca: "/usr/local/etc/nebula/' . $uuid . '/ca.crt"', $yaml);

        // firewall: block must carry BOTH the scalar actions AND the rule lists
        $this->assertStringContainsString('firewall:', $yaml);
        $this->assertStringContainsString('outbound_action: "drop"', $yaml);
        $this->assertStringContainsString('inbound_action: "drop"', $yaml);
        $this->assertStringContainsString('outbound:', $yaml);
        $this->assertStringContainsString('inbound:', $yaml);
        $this->assertStringContainsString('proto: "any"', $yaml);

        // omitted optional must NOT appear
        $this->assertStringNotContainsString('read_buffer', $yaml);

        // bools render bare (no quotes), ints bare, strings quoted
        $this->assertStringNotContainsString('punch: "false"', $yaml);
        $this->assertStringNotContainsString('mtu: "1300"', $yaml);
        $this->assertStringNotContainsString('cipher: aes', $yaml);
    }

    // -------------------------------------------------------------------------
    // pki.blocklist rendering
    // -------------------------------------------------------------------------

    /** A pair of valid 64-char lowercase hex sha256 fingerprints. */
    private const BL_FP_A = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private const BL_FP_B = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';

    /** Add a blocklist entry to $model and return the entry node. */
    private function addBlock(Nebula $model, string $fingerprint, array $fields = [])
    {
        $entry = $model->pki->blocklist->entry->Add();
        $entry->enabled     = '1';
        $entry->scope       = 'global'; // default; override via $fields for per-instance
        $entry->instance    = '';
        $entry->fingerprint = $fingerprint;
        foreach ($fields as $k => $v) {
            $entry->$k = $v;
        }
        return $entry;
    }

    /**
     * A global blocklist entry (empty instance) renders for every instance.
     */
    public function testGlobalBlocklistEntryAppearsForEveryInstance()
    {
        $model = new Nebula();

        $nodeA = $model->instances->instance->Add();
        $nodeA->enabled = '1'; $nodeA->description = 'bl-A';
        $nodeA->listen_host = '0.0.0.0'; $nodeA->listen_port = '4242';
        $nodeA->am_lighthouse = '0';

        $nodeB = $model->instances->instance->Add();
        $nodeB->enabled = '1'; $nodeB->description = 'bl-B';
        $nodeB->listen_host = '0.0.0.0'; $nodeB->listen_port = '4243';
        $nodeB->am_lighthouse = '0';

        // One global block.
        $this->addBlock($model, self::BL_FP_A);

        $yamlA = $model->generateConfig($nodeA);
        $yamlB = $model->generateConfig($nodeB);

        // blocklist: is nested under pki: with the fingerprint as a quoted list item.
        $this->assertStringContainsString('blocklist:', $yamlA);
        $this->assertStringContainsString('- "' . self::BL_FP_A . '"', $yamlA);
        $this->assertStringContainsString('- "' . self::BL_FP_A . '"', $yamlB);
    }

    /**
     * A per-instance blocklist entry renders only for its own instance.
     */
    public function testPerInstanceBlocklistEntryScopedToItsInstance()
    {
        $model = new Nebula();

        $nodeA = $model->instances->instance->Add();
        $nodeA->enabled = '1'; $nodeA->description = 'bl-scope-A';
        $nodeA->listen_host = '0.0.0.0'; $nodeA->listen_port = '4242';
        $nodeA->am_lighthouse = '0';
        $uuidA = $nodeA->getAttribute('uuid');

        $nodeB = $model->instances->instance->Add();
        $nodeB->enabled = '1'; $nodeB->description = 'bl-scope-B';
        $nodeB->listen_host = '0.0.0.0'; $nodeB->listen_port = '4243';
        $nodeB->am_lighthouse = '0';

        // Block scoped to instance A only (explicit instance scope).
        $this->addBlock($model, self::BL_FP_A, ['scope' => 'instance', 'instance' => $uuidA]);

        $yamlA = $model->generateConfig($nodeA);
        $yamlB = $model->generateConfig($nodeB);

        // A sees the block; B does not (and B has no blocklist key at all).
        $this->assertStringContainsString('- "' . self::BL_FP_A . '"', $yamlA);
        $this->assertStringNotContainsString(self::BL_FP_A, $yamlB);
        $this->assertStringNotContainsString('blocklist:', $yamlB);
    }

    /**
     * Disabled blocklist entries are skipped.
     */
    public function testDisabledBlocklistEntryIsSkipped()
    {
        [$model, $node, $uuid] = $this->makeInstance('bl-disabled');

        $this->addBlock($model, self::BL_FP_A, ['enabled' => '0']);

        $yaml = $model->generateConfig($node);

        $this->assertStringNotContainsString(self::BL_FP_A, $yaml);
        // No enabled entries → the blocklist key is omitted entirely.
        $this->assertStringNotContainsString('blocklist:', $yaml);
    }

    /**
     * Block-until-purged: the renderer ignores Expiry entirely. An enabled entry
     * renders whether its Expiry date is in the past or the future — Expiry is now
     * only a purge-eligibility marker, not an effective end date. (The expired one
     * is removed only when the admin runs Purge expired, covered in
     * BlocklistCRUDTest::testPurgeExpiredRemovesOnlyPastDatedEntries.)
     */
    public function testExpiredBlocklistEntryStillRenders()
    {
        [$model, $node, $uuid] = $this->makeInstance('bl-expiry');

        // Expired yesterday — still blocks until purged.
        $this->addBlock($model, self::BL_FP_A, ['expiry' => date('Y-m-d', strtotime('-1 day'))]);
        // Future-dated — also blocks.
        $this->addBlock($model, self::BL_FP_B, ['expiry' => date('Y-m-d', strtotime('+1 year'))]);

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('- "' . self::BL_FP_A . '"', $yaml, 'expired entry still blocks until purged');
        $this->assertStringContainsString('- "' . self::BL_FP_B . '"', $yaml, 'future-dated entry blocks');
    }

    /**
     * An empty / no-entry blocklist omits the pki.blocklist key entirely.
     */
    public function testEmptyBlocklistOmitsKey()
    {
        [$model, $node, $uuid] = $this->makeInstance('bl-empty');

        $yaml = $model->generateConfig($node);

        $this->assertStringNotContainsString('blocklist:', $yaml);
        // The fixed pki: block must still be present.
        $this->assertStringContainsString('pki:', $yaml);
    }

    // Note: the renderer no longer consults Expiry at all (block-until-purged),
    // so empty / non-ISO / unparseable expiry values all render the same way —
    // see testExpiredBlocklistEntryStillRenders above. The date-format parsing
    // that decides *purge* eligibility is covered by
    // BlocklistCRUDTest::testPurgeExpiredHandlesDateFormats.

    // -------------------------------------------------------------------------
    // tun.unsafe_routes rendering
    // -------------------------------------------------------------------------

    /** Add an unsafe route to $model and return the node. */
    private function addRoute(Nebula $model, string $instUuid, array $fields = [])
    {
        $r = $model->unsafe_routes->route->Add();
        $r->enabled  = '1';
        $r->instance = $instUuid;
        $r->route    = '172.16.1.0/24';
        $r->via      = '192.168.100.99';
        $r->install  = '1';
        foreach ($fields as $k => $v) {
            $r->$k = $v;
        }
        return $r;
    }

    public function testUnsafeRouteRendersForItsInstanceOnly()
    {
        $model = new Nebula();
        $a = $model->instances->instance->Add();
        $a->enabled = '1'; $a->description = 'ur-A';
        $a->listen_host = '0.0.0.0'; $a->listen_port = '4242'; $a->am_lighthouse = '0';
        $uuidA = $a->getAttribute('uuid');

        $b = $model->instances->instance->Add();
        $b->enabled = '1'; $b->description = 'ur-B';
        $b->listen_host = '0.0.0.0'; $b->listen_port = '4243'; $b->am_lighthouse = '0';

        $this->addRoute($model, $uuidA, ['route' => '10.9.0.0/16', 'via' => '192.168.100.5']);

        $yamlA = $model->generateConfig($a);
        $yamlB = $model->generateConfig($b);

        $this->assertStringContainsString('unsafe_routes:', $yamlA);
        $this->assertStringContainsString('route: "10.9.0.0/16"', $yamlA);
        $this->assertStringContainsString('via: "192.168.100.5"', $yamlA);
        $this->assertStringContainsString('install: true', $yamlA);
        $this->assertStringNotContainsString('unsafe_routes:', $yamlB);
    }

    public function testUnsafeRouteOptionalFieldsOmittedWhenEmpty()
    {
        [$model, $node, $uuid] = $this->makeInstance('ur-opt');
        $this->addRoute($model, $uuid); // no mtu/metric

        $yaml = $model->generateConfig($node);

        // metric: is only emitted by unsafe routes (tun.mtu's default makes a bare
        // "mtu:" check ambiguous, so we assert on metric for the omitted case).
        $this->assertStringContainsString('route: "172.16.1.0/24"', $yaml);
        $this->assertStringNotContainsString('metric:', $yaml);
    }

    public function testUnsafeRouteMtuMetricRenderedWhenSet()
    {
        [$model, $node, $uuid] = $this->makeInstance('ur-mtu');
        // 8800 is distinct from the tun.mtu default (1300) so it uniquely
        // identifies the route-level mtu.
        $this->addRoute($model, $uuid, ['mtu' => '8800', 'metric' => '100']);

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('mtu: 8800', $yaml);
        $this->assertStringContainsString('metric: 100', $yaml);
    }

    public function testDisabledUnsafeRouteSkipped()
    {
        [$model, $node, $uuid] = $this->makeInstance('ur-off');
        $this->addRoute($model, $uuid, ['enabled' => '0']);

        $yaml = $model->generateConfig($node);

        $this->assertStringNotContainsString('unsafe_routes:', $yaml);
    }

    // -------------------------------------------------------------------------
    // tun.routes (per-route MTU override) rendering
    // -------------------------------------------------------------------------

    /** Add a tun.routes MTU override and return the node. */
    private function addTunRoute(Nebula $model, string $instUuid, array $fields = [])
    {
        $r = $model->tun_routes->route->Add();
        $r->enabled  = '1';
        $r->instance = $instUuid;
        $r->route    = '10.0.0.0/16';
        $r->mtu      = '8800';
        foreach ($fields as $k => $v) {
            $r->$k = $v;
        }
        return $r;
    }

    public function testTunRouteRendersForItsInstanceOnly()
    {
        $model = new Nebula();
        $a = $model->instances->instance->Add();
        $a->enabled = '1'; $a->description = 'tr-A';
        $a->listen_host = '0.0.0.0'; $a->listen_port = '4242'; $a->am_lighthouse = '0';
        $uuidA = $a->getAttribute('uuid');

        $b = $model->instances->instance->Add();
        $b->enabled = '1'; $b->description = 'tr-B';
        $b->listen_host = '0.0.0.0'; $b->listen_port = '4243'; $b->am_lighthouse = '0';

        $this->addTunRoute($model, $uuidA, ['route' => '10.7.0.0/16', 'mtu' => '8000']);

        $yamlA = $model->generateConfig($a);
        $yamlB = $model->generateConfig($b);

        // Rendered under tun.routes as {route, mtu}.
        $this->assertStringContainsString('routes:', $yamlA);
        $this->assertStringContainsString('route: "10.7.0.0/16"', $yamlA);
        $this->assertStringContainsString('mtu: 8000', $yamlA);
        $this->assertStringNotContainsString('10.7.0.0/16', $yamlB);
    }

    public function testDisabledTunRouteSkipped()
    {
        [$model, $node, $uuid] = $this->makeInstance('tr-off');
        $this->addTunRoute($model, $uuid, ['enabled' => '0', 'route' => '10.8.0.0/16']);

        $yaml = $model->generateConfig($node);

        $this->assertStringNotContainsString('10.8.0.0/16', $yaml);
    }

    // -------------------------------------------------------------------------
    // sshd debug server — always on, internal management channel only
    // -------------------------------------------------------------------------

    /**
     * The sshd block is ALWAYS emitted: enabled, bound to 127.0.0.1 on the
     * per-instance derived port, with a per-instance host key. When a diag
     * pubkey is supplied, only the _opnsense_diag user is authorized.
     */
    public function testSshdAlwaysOnWithDiagKeyOnly()
    {
        [$model, $node, $uuid] = $this->makeInstance('sshd-always');

        $yaml = $model->generateConfig($node, 'ssh-ed25519 AAAADIAGKEY diag');

        $this->assertStringContainsString('sshd:', $yaml);
        $this->assertStringContainsString('enabled: true', $yaml);
        // Bound to localhost on the derived port (and nothing else).
        $port = $model->sshdPortFor($uuid);
        $this->assertStringContainsString('listen: "127.0.0.1:' . $port . '"', $yaml);
        $this->assertStringContainsString('host_key: "/usr/local/etc/nebula/' . $uuid . '/sshd_host_key"', $yaml);
        // Only the plugin diagnostics user is authorized; no user lists.
        $this->assertStringContainsString('user: "_opnsense_diag"', $yaml);
        $this->assertStringContainsString('- "ssh-ed25519 AAAADIAGKEY diag"', $yaml);
        $this->assertStringNotContainsString('trusted_cas:', $yaml);
    }

    /**
     * The derived sshd port is stable for a given instance and distinct across
     * instances (each daemon needs its own 127.0.0.1 port).
     */
    public function testSshdPortDistinctPerInstance()
    {
        $model = new Nebula();
        $a = $model->instances->instance->Add();
        $a->enabled = '1'; $a->description = 'a';
        $a->listen_host = '0.0.0.0'; $a->listen_port = '4242'; $a->am_lighthouse = '0';
        $b = $model->instances->instance->Add();
        $b->enabled = '1'; $b->description = 'b';
        $b->listen_host = '0.0.0.0'; $b->listen_port = '4243'; $b->am_lighthouse = '0';

        $pa = $model->sshdPortFor($a->getAttribute('uuid'));
        $pb = $model->sshdPortFor($b->getAttribute('uuid'));

        $this->assertNotSame($pa, $pb, 'two instances must get distinct sshd ports');
        $this->assertSame($pa, $model->sshdPortFor($a->getAttribute('uuid')), 'port must be stable');
        $this->assertGreaterThanOrEqual(22000, $pa);
        $this->assertLessThan(26000, $pa);
    }

    /**
     * Without a diag pubkey (a bare render, e.g. nebula -test), the sshd block is
     * still present (enabled/listen/host_key) but carries no authorized_users.
     */
    public function testSshdBareRenderHasNoAuthorizedUsers()
    {
        [$model, $node, $uuid] = $this->makeInstance('sshd-bare');

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('sshd:', $yaml);
        $this->assertStringContainsString('enabled: true', $yaml);
        $this->assertStringNotContainsString('authorized_users:', $yaml);
    }

    // -------------------------------------------------------------------------
    // stats (graphite/prometheus telemetry) rendering
    // -------------------------------------------------------------------------

    /**
     * Blank stats_type (the default) emits no stats: block at all.
     */
    public function testStatsOmittedWhenTypeBlank()
    {
        [$model, $node, $uuid] = $this->makeInstance('stats-off');

        $yaml = $model->generateConfig($node);

        $this->assertStringNotContainsString('stats:', $yaml);
    }

    /**
     * graphite type emits the graphite keys (prefix/protocol/host) + interval and
     * does NOT leak prometheus-only keys.
     */
    public function testStatsGraphiteRendersGraphiteKeysOnly()
    {
        [$model, $node, $uuid] = $this->makeInstance('stats-graphite');
        $node->stats_type     = 'graphite';
        $node->stats_interval = '15s';
        $node->stats_prefix   = 'nebula';
        $node->stats_protocol = 'tcp';
        $node->stats_host     = '127.0.0.1:9999';

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('stats:', $yaml);
        $this->assertStringContainsString('type: "graphite"', $yaml);
        $this->assertStringContainsString('interval: "15s"', $yaml);
        $this->assertStringContainsString('prefix: "nebula"', $yaml);
        $this->assertStringContainsString('protocol: "tcp"', $yaml);
        $this->assertStringContainsString('host: "127.0.0.1:9999"', $yaml);

        // prometheus-only keys must be absent.
        $this->assertStringNotContainsString('namespace:', $yaml);
        $this->assertStringNotContainsString('subsystem:', $yaml);
    }

    /**
     * prometheus type emits the prometheus keys (listen/path/namespace/subsystem)
     * + interval and does NOT leak graphite-only keys.
     */
    public function testStatsPrometheusRendersPrometheusKeysOnly()
    {
        [$model, $node, $uuid] = $this->makeInstance('stats-prometheus');
        $node->stats_type      = 'prometheus';
        $node->stats_interval  = '10s';
        $node->stats_listen    = '127.0.0.1:8080';
        $node->stats_path      = '/metrics';
        $node->stats_namespace = 'nebulans';
        $node->stats_subsystem = 'nebula';

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('stats:', $yaml);
        $this->assertStringContainsString('type: "prometheus"', $yaml);
        $this->assertStringContainsString('listen: "127.0.0.1:8080"', $yaml);
        $this->assertStringContainsString('path: "/metrics"', $yaml);
        $this->assertStringContainsString('namespace: "nebulans"', $yaml);
        $this->assertStringContainsString('subsystem: "nebula"', $yaml);

        // graphite-only keys must be absent.
        $this->assertStringNotContainsString('prefix:', $yaml);
        $this->assertStringNotContainsString('protocol:', $yaml);
    }

    /**
     * The two metric toggles render as bare booleans only when enabled.
     */
    public function testStatsMetricTogglesEmittedOnlyWhenEnabled()
    {
        [$model, $node, $uuid] = $this->makeInstance('stats-toggles');
        $node->stats_type               = 'prometheus';
        $node->stats_listen             = '127.0.0.1:8080';
        $node->stats_message_metrics    = '1';
        $node->stats_lighthouse_metrics = '0';

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('message_metrics: true', $yaml);
        // The disabled toggle is omitted entirely (not emitted as false).
        $this->assertStringNotContainsString('lighthouse_metrics:', $yaml);
    }

    // -------------------------------------------------------------------------
    // lighthouse allow-lists rendering
    // -------------------------------------------------------------------------

    /**
     * remote_allow_list: "CIDR = bool" lines render as a map under lighthouse.
     * An IPv6 CIDR key (leading colon) must be quoted so it stays a valid key.
     */
    public function testLighthouseRemoteAllowListRendersMap()
    {
        [$model, $node, $uuid] = $this->makeInstance('lh-remote-allow');
        $node->lighthouse_remote_allow_list = "10.0.0.0/8 = false\n10.42.42.0/24 = true\n::1/128 = false";

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('lighthouse:', $yaml);
        $this->assertStringContainsString('remote_allow_list:', $yaml);
        $this->assertStringContainsString('10.0.0.0/8: false', $yaml);
        $this->assertStringContainsString('10.42.42.0/24: true', $yaml);
        // IPv6 CIDR key must be quoted (bare ::1/128 as a key mis-parses).
        $this->assertStringContainsString('"::1/128": false', $yaml);
    }

    /**
     * local_allow_list: plain CIDR lines plus the "interface <regex>" form,
     * which lands under a nested interfaces: map (the regex key gets quoted).
     */
    public function testLighthouseLocalAllowListWithInterfaces()
    {
        [$model, $node, $uuid] = $this->makeInstance('lh-local-allow');
        $node->lighthouse_local_allow_list = "10.0.0.0/8 = true\ninterface docker.* = false";

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('local_allow_list:', $yaml);
        $this->assertStringContainsString('10.0.0.0/8: true', $yaml);
        $this->assertStringContainsString('interfaces:', $yaml);
        // The interface regex key is quoted because it contains '*'.
        $this->assertStringContainsString('"docker.*": false', $yaml);
    }

    /**
     * remote_allow_ranges (experimental): "<vpn> <remote> = bool" renders a
     * map<vpn, map<remote, bool>>.
     */
    public function testLighthouseRemoteAllowRangesRendersNestedMap()
    {
        [$model, $node, $uuid] = $this->makeInstance('lh-ranges');
        $node->lighthouse_remote_allow_ranges = "10.42.42.0/24 192.168.0.0/16 = true";

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('remote_allow_ranges:', $yaml);
        $this->assertStringContainsString('10.42.42.0/24:', $yaml);
        $this->assertStringContainsString('192.168.0.0/16: true', $yaml);
    }

    /**
     * calculated_remotes (experimental): "<vpn> <mask> <port>" renders a
     * map<vpn, list<{mask, port}>>; repeated vpn-cidr appends to the list.
     */
    public function testLighthouseCalculatedRemotesRendersListOfStructs()
    {
        [$model, $node, $uuid] = $this->makeInstance('lh-calc');
        $node->lighthouse_calculated_remotes = "192.168.1.0/24 192.168.1.0/24 4242\n"
            . "192.168.1.0/24 192.168.1.0/24 4243";

        $yaml = $model->generateConfig($node);

        $this->assertStringContainsString('calculated_remotes:', $yaml);
        $this->assertStringContainsString('192.168.1.0/24:', $yaml);
        $this->assertStringContainsString('mask: "192.168.1.0/24"', $yaml);
        // port is an int -> bare; both repeated entries present.
        $this->assertStringContainsString('port: 4242', $yaml);
        $this->assertStringContainsString('port: 4243', $yaml);
    }

    /**
     * Malformed allow-list lines (no '=', unrecognized bool) are skipped, and an
     * all-empty set adds no lighthouse allow-list keys.
     */
    public function testLighthouseAllowListMalformedAndEmptySkipped()
    {
        [$model, $node, $uuid] = $this->makeInstance('lh-malformed');
        $node->lighthouse_remote_allow_list = "this has no equals\n10.0.0.0/8 = maybe";

        $yaml = $model->generateConfig($node);

        $this->assertStringNotContainsString('remote_allow_list:', $yaml);
        $this->assertStringNotContainsString('this has no equals', $yaml);
    }
}
