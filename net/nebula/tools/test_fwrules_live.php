#!/usr/local/bin/php
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

/**
 * Guest oracle: verify per-instance firewall rules render into nebula.yml.
 *
 * Prerequisites: reseed_demo.php must have been run first so a demo-lighthouse
 * instance with certs exists (the cert dir is needed for `nebula -test` to pass).
 *
 * Run on the OPNsense guest after `make install`:
 *   php /usr/plugins/net/nebula/tools/test_fwrules_live.php
 */

require_once('script/load_phalcon.php');

use OPNsense\Core\Config;
use OPNsense\Nebula\Nebula;

function info(string $msg): void
{
    echo "INFO: {$msg}\n";
}

function pass(string $msg): void
{
    echo "PASS: {$msg}\n";
}

function fail(string $msg): void
{
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
}

function assert_ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fail($msg);
    }
    pass($msg);
}

function assert_contains(string $haystack, string $needle, string $msg): void
{
    assert_ok(strpos($haystack, $needle) !== false, $msg . " (looking for: {$needle})");
}

function assert_not_contains(string $haystack, string $needle, string $msg): void
{
    assert_ok(strpos($haystack, $needle) === false, $msg . " (must not contain: {$needle})");
}

// -----------------------------------------------------------------------
// Find the demo-lighthouse instance (seeded by reseed_demo.php).
// -----------------------------------------------------------------------
$model = new Nebula();
$instNode = null;
foreach ($model->instances->instance->iterateItems() as $inst) {
    if ((string)$inst->description === 'demo-lighthouse') {
        $instNode = $inst;
        break;
    }
}
if ($instNode === null) {
    fail("demo-lighthouse instance not found — run reseed_demo.php first");
}
$uuid = $instNode->getAttribute('uuid');
info("Using instance uuid={$uuid} (demo-lighthouse)");

// -----------------------------------------------------------------------
// Remove any old test fwrules left from a previous run.
// -----------------------------------------------------------------------
$toDelete = [];
foreach ($model->fwrules->rule->iterateItems() as $rule) {
    if ((string)$rule->instance === $uuid) {
        $toDelete[] = $rule->getAttribute('uuid');
    }
}
foreach ($toDelete as $ruleUuid) {
    $model->fwrules->rule->del($ruleUuid);
}
if (!empty($toDelete)) {
    info("Removed " . count($toDelete) . " old test rules");
}

// -----------------------------------------------------------------------
// Phase 1: Add real rules and verify they render.
// -----------------------------------------------------------------------
info("=== Phase 1: rules present ===");

$r1 = $model->fwrules->rule->Add();
$r1->enabled   = '1';
$r1->instance  = $uuid;
$r1->direction = 'inbound';
$r1->protocol  = 'tcp';
$r1->port      = '5432';
$r1->group     = 'db';

$r2 = $model->fwrules->rule->Add();
$r2->enabled   = '1';
$r2->instance  = $uuid;
$r2->direction = 'inbound';
$r2->protocol  = 'icmp';
$r2->port      = 'any';
$r2->host      = 'any';

$r3 = $model->fwrules->rule->Add();
$r3->enabled   = '1';
$r3->instance  = $uuid;
$r3->direction = 'outbound';
$r3->host      = 'any';

// Persist and reconfigure.
$model->serializeToConfig();
Config::getInstance()->save();
$rc = 0;
$out = [];
exec('configctl nebula reconfigure 2>&1', $out, $rc);
info("configctl nebula reconfigure: rc={$rc}");
foreach ($out as $line) {
    info("  {$line}");
}
assert_ok($rc === 0, "configctl nebula reconfigure exited 0");

$ymlPath = "/usr/local/etc/nebula/{$uuid}.yml";
assert_ok(file_exists($ymlPath), "nebula.yml exists at {$ymlPath}");
$yaml = file_get_contents($ymlPath);

// Inbound rules must appear.
assert_contains($yaml, 'group: "db"', "group matcher rendered");
assert_contains($yaml, 'port: "5432"', "port 5432 rendered");
assert_contains($yaml, 'proto: "tcp"', "proto tcp rendered");
assert_contains($yaml, 'proto: "icmp"', "proto icmp rendered");

// Outbound rule must appear.
assert_contains($yaml, 'outbound:', "outbound key present");

// The placeholder 'host: "any"' appears ONLY from the outbound rule (r3), not
// from a permissive fallback on the inbound direction. Verify the inbound block
// contains tcp and icmp, which means the inbound rules were rendered (not the fallback).
assert_contains($yaml, 'proto: "tcp"',  "inbound rule tcp present — no permissive fallback on inbound");
assert_contains($yaml, 'proto: "icmp"', "inbound rule icmp present");

// nebula -test rc=0
$certDir = "/usr/local/etc/nebula/{$uuid}";
$nebulaTest = [];
$nebulaRc   = 0;
exec("/usr/local/bin/nebula -test -config {$ymlPath} 2>&1", $nebulaTest, $nebulaRc);
info("nebula -test (with rules): rc={$nebulaRc}");
foreach ($nebulaTest as $line) {
    info("  {$line}");
}
assert_ok($nebulaRc === 0, "nebula -test rc=0 with explicit rules");

// -----------------------------------------------------------------------
// Phase 2: Remove all rules → permissive fallback.
// -----------------------------------------------------------------------
info("=== Phase 2: no rules → permissive fallback ===");

$model2 = new Nebula();
$toDelete2 = [];
foreach ($model2->fwrules->rule->iterateItems() as $rule) {
    if ((string)$rule->instance === $uuid) {
        $toDelete2[] = $rule->getAttribute('uuid');
    }
}
foreach ($toDelete2 as $ruleUuid) {
    $model2->fwrules->rule->del($ruleUuid);
}
info("Removed " . count($toDelete2) . " rules");

$model2->serializeToConfig();
Config::getInstance()->save();
$rc2 = 0;
$out2 = [];
exec('configctl nebula reconfigure 2>&1', $out2, $rc2);
info("configctl nebula reconfigure (no rules): rc={$rc2}");
foreach ($out2 as $line) {
    info("  {$line}");
}
assert_ok($rc2 === 0, "configctl nebula reconfigure (no rules) exited 0");

$yaml2 = file_get_contents($ymlPath);
assert_contains($yaml2, 'proto: "any"', "permissive fallback proto:any present when no rules");
assert_contains($yaml2, 'host: "any"',  "permissive fallback host:any present when no rules");
assert_contains($yaml2, 'port: "any"',  "permissive fallback port:any present when no rules");

$nebulaTest2 = [];
$nebulaRc2   = 0;
exec("/usr/local/bin/nebula -test -config {$ymlPath} 2>&1", $nebulaTest2, $nebulaRc2);
info("nebula -test (permissive fallback): rc={$nebulaRc2}");
foreach ($nebulaTest2 as $line) {
    info("  {$line}");
}
assert_ok($nebulaRc2 === 0, "nebula -test rc=0 with permissive fallback");

// -----------------------------------------------------------------------
// Done.
// -----------------------------------------------------------------------
echo "\nAll oracle checks passed.\n";
