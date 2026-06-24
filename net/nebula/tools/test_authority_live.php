#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
 * All rights reserved.
 *
 * Live integration test for AuthorityController logic (generate + import).
 *
 * Run on the OPNsense guest after `make install`:
 *   php /usr/plugins/net/nebula/tools/test_authority_live.php
 *
 * This script exercises the same Backend/configd + model-store path that
 * generateAction() and importAction() use, without the Phalcon HTTP layer.
 * It calls pki.php directly (via shell) and stores the result in the model.
 *
 * Exit 0 = all assertions passed; exit 1 = failure (message printed).
 */

// Bootstrap OPNsense MVC environment.
// Uses the same loader/config that phpunit uses, but points configDir at the
// live /conf directory so Config::getInstance() reads the real config.xml.
$loaderPath = '/usr/local/opnsense/mvc/app/config/loader.php';
if (!file_exists($loaderPath)) {
    fwrite(STDERR, "ERROR: OPNsense MVC not found — run `make install` first.\n");
    exit(1);
}

require_once '/usr/local/opnsense/mvc/app/config/AppConfig.php';
$config = new OPNsense\Core\AppConfig([
    'application' => [
        'baseUri'        => '/',
        'controllersDir' => '/usr/local/opnsense/mvc/app/controllers/',
        'modelsDir'      => '/usr/local/opnsense/mvc/app/models/',
        'viewsDir'       => '/usr/local/opnsense/mvc/app/views/',
        'pluginsDir'     => '/usr/local/opnsense/mvc/app/plugins/',
        'libraryDir'     => '/usr/local/opnsense/mvc/app/library/',
        'contribDir'     => '/usr/local/opnsense/contrib',
        'configDefault'  => '/conf/config.xml',
        'configDir'      => '/conf',
        'cacheDir'       => '/tmp/live-test-cache',
        'tempDir'        => '/tmp/live-test-tmp',
    ],
    'globals' => [
        'debug'         => false,
        'owner'         => 'root:wheel',
        'simulate_mode' => false,
    ],
]);

require_once $loaderPath;

// Ensure cache/temp dirs exist.
@mkdir('/tmp/live-test-cache', 0700, true);
@mkdir('/tmp/live-test-tmp',   0700, true);

use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Nebula\Nebula;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

function call_pki(string $action, array $params): array
{
    $b64 = base64_encode(json_encode($params));
    $out = (new Backend())->configdpRun("nebula {$action}", [$b64]);
    $res = json_decode($out, true);
    if (!is_array($res)) {
        return ['error' => "configd returned non-JSON: " . var_export($out, true)];
    }
    return $res;
}

function find_by_uuid($arrayField, string $uuid)
{
    foreach ($arrayField->iterateItems() as $node) {
        if ($node->getAttribute('uuid') === $uuid) {
            return $node;
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
// Test 1: Generate CA via configd, store in model, verify crt validates as CA
// ---------------------------------------------------------------------------

echo "\n=== Test 1: generate CA ===\n";

$genRes = call_pki('pki_generate_ca', [
    'name'           => 'live-test-ca',
    'curve'          => '25519',
    'duration_hours' => 8760,  // 1 year
]);

assert_true(empty($genRes['error']), "generate-ca configd call succeeded (error: " . ($genRes['error'] ?? 'none') . ")");
assert_true(!empty($genRes['crt']),  "generate-ca returned non-empty crt");
assert_true(!empty($genRes['key']),  "generate-ca returned non-empty key");

$crt = $genRes['crt'];
$key = $genRes['key'];

// Store in model.
$mdl  = new Nebula();
$node = $mdl->pki->authorities->authority->Add();
$node->descr  = 'live-test-ca';
$node->origin = 'generated';
$node->curve  = '25519';
$node->crt    = $crt;
$node->key    = $key;

$uuid = $node->getAttribute('uuid');
assert_true(!empty($uuid), "Add() assigned a UUID");

$valMsgs = $mdl->performValidation();
assert_true(count($valMsgs) === 0, "model validation passes after generate (errors: " . implode(', ', array_map(fn($m) => $m->getMessage(), iterator_to_array($valMsgs))) . ")");

$mdl->serializeToConfig();
Config::getInstance()->save();
echo "INFO: authority saved with uuid={$uuid}\n";

// Reload and verify the authority persisted.
$mdl2  = new Nebula();
$found = find_by_uuid($mdl2->pki->authorities->authority, $uuid);
assert_true($found !== null,          "authority persists after save+reload");
assert_true(!empty((string)$found->crt), "reloaded crt is non-empty");
assert_true(!empty((string)$found->key), "reloaded key is non-empty");

// Validate the stored crt via pki_print_cert.
$printRes = call_pki('pki_print_cert', ['crt' => (string)$found->crt]);
assert_true(empty($printRes['error']),     "pki_print_cert accepts the generated CA crt");
assert_true(!empty($printRes['info']),     "pki_print_cert info is non-empty");

// nebula-cert print -json returns an array of cert objects; isCa is at [0].details.isCa.
$isCa = $printRes['info'][0]['details']['isCa'] ?? false;
assert_true($isCa === true, "generated CA has isCa=true in print output");

// ---------------------------------------------------------------------------
// Test 2: Import a valid CA cert (the one we just generated) → succeeds
// ---------------------------------------------------------------------------

echo "\n=== Test 2: import valid CA ===\n";

// Re-run print-cert to confirm the validation path works.
$printRes2 = call_pki('pki_print_cert', ['crt' => $crt]);
assert_true(empty($printRes2['error']), "pki_print_cert accepts crt for import validation");

$mdl3  = new Nebula();
$node3 = $mdl3->pki->authorities->authority->Add();
$node3->descr  = 'live-imported-ca';
$node3->origin = 'imported';
$node3->curve  = '25519';
$node3->crt    = $crt;
// key intentionally omitted (cert-only import).

$uuid3   = $node3->getAttribute('uuid');
$msgs3   = $mdl3->performValidation();
assert_true(count($msgs3) === 0, "import (no key) passes model validation");

$mdl3->serializeToConfig();
Config::getInstance()->save();

$mdl3r  = new Nebula();
$found3 = find_by_uuid($mdl3r->pki->authorities->authority, $uuid3);
assert_true($found3 !== null, "imported authority persists after save");
assert_true((string)$found3->origin === 'imported', "imported authority has origin=imported");

// ---------------------------------------------------------------------------
// Test 3: Import garbage crt → pki_print_cert rejects it
// ---------------------------------------------------------------------------

echo "\n=== Test 3: import garbage crt — must be rejected ===\n";

$badPrint = call_pki('pki_print_cert', ['crt' => 'garbage-not-a-cert']);
$rejected = !empty($badPrint['error']) || empty($badPrint['info']);
assert_true($rejected, "pki_print_cert rejects garbage crt (error: " . ($badPrint['error'] ?? 'no info returned') . ")");

// ---------------------------------------------------------------------------
// Cleanup: remove the two authorities we wrote.
// ---------------------------------------------------------------------------

echo "\n=== Cleanup ===\n";

$mdlClean = new Nebula();
$r1 = $mdlClean->pki->authorities->authority->del($uuid);
$r3 = $mdlClean->pki->authorities->authority->del($uuid3);
assert_true($r1, "del generated authority");
assert_true($r3, "del imported authority");
$mdlClean->serializeToConfig();
Config::getInstance()->save();
echo "INFO: cleanup complete\n";

echo "\n=== All live tests PASSED ===\n";
exit(0);
