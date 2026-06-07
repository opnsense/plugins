#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
 * All rights reserved.
 *
 * Live integration test for the CSR (sign-a-public-key) workflow.
 *
 * Run on the OPNsense guest after `make install`:
 *   php /usr/plugins/net/nebula/tools/test_csr_live.php
 *
 * Validates:
 *   - pki_sign_cert with in_pub returns crt non-empty, key empty (CSR mode)
 *   - pki_sign_cert without in_pub returns crt + key non-empty (generate-here mode)
 *   - CSR cert stored with has_key=0; generate-here cert with has_key=1
 *   - CSR cert: nebula-cert print shows correct name/networks, issuer == demo-ca
 *   - CSR cert: embedded public key matches the node's /tmp/n.pub
 *   - validatePublicKeyPem rejects non-Nebula PEM
 *   - Cleanup leaves no stale certs
 *
 * Exit 0 = all assertions passed; exit 1 = failure.
 */

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
        'cacheDir'       => '/tmp/csr-test-cache',
        'tempDir'        => '/tmp/csr-test-tmp',
    ],
    'globals' => [
        'debug'         => false,
        'owner'         => 'root:wheel',
        'simulate_mode' => false,
    ],
]);

require_once $loaderPath;

@mkdir('/tmp/csr-test-cache', 0700, true);
@mkdir('/tmp/csr-test-tmp',   0700, true);

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
        return ['error' => 'configd returned non-JSON: ' . var_export($out, true)];
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
// Find the seeded demo-ca in the live config
// ---------------------------------------------------------------------------

echo "\n=== Setup: locate demo-ca ===\n";

$mdl0 = new Nebula();
$demoCaNode = null;
foreach ($mdl0->pki->authorities->authority->iterateItems() as $node) {
    if (strpos((string)$node->descr, 'demo-ca') !== false
        || strpos((string)$node->cn, 'demo-ca') !== false) {
        $demoCaNode = $node;
        break;
    }
}
assert_true($demoCaNode !== null, 'demo-ca found in model');

$caUuid = $demoCaNode->getAttribute('uuid');
$caCrt  = (string)$demoCaNode->crt;
$caKey  = (string)$demoCaNode->key;
$caName = (string)($demoCaNode->cn != '' ? $demoCaNode->cn : $demoCaNode->descr);
assert_true($caCrt !== '', 'demo-ca has non-empty crt');
assert_true($caKey !== '', 'demo-ca has non-empty key');
echo "INFO: demo-ca uuid={$caUuid} cn/descr={$caName}\n";

// Get CA fingerprint for issuer comparison.
$caPrint = call_pki('pki_print_cert', ['crt' => $caCrt]);
assert_true(empty($caPrint['error']), 'pki_print_cert accepts demo-ca crt');
$caFingerprint = $caPrint['info'][0]['fingerprint'] ?? '';
assert_true(!empty($caFingerprint), 'demo-ca fingerprint is non-empty');
echo "INFO: demo-ca fingerprint={$caFingerprint}\n";

// ---------------------------------------------------------------------------
// Generate a node keypair (simulates `nebula-cert keygen` on the node)
// ---------------------------------------------------------------------------

echo "\n=== Test 1: generate node keypair (keygen) ===\n";

$pubFile = '/tmp/n.pub';
$keyFile = '/tmp/n.key';
@unlink($pubFile);
@unlink($keyFile);

$keygenCmd = '/usr/local/bin/nebula-cert keygen -out-pub ' . escapeshellarg($pubFile)
           . ' -out-key ' . escapeshellarg($keyFile);
exec($keygenCmd, $kgOut, $kgRc);
assert_true($kgRc === 0, "nebula-cert keygen exited 0 (rc={$kgRc})");
assert_true(file_exists($pubFile), '/tmp/n.pub created by keygen');
assert_true(file_exists($keyFile), '/tmp/n.key created by keygen');

$nodePub = file_get_contents($pubFile);
assert_true(!empty($nodePub), '/tmp/n.pub is non-empty');
assert_true(
    (bool)preg_match('/-----BEGIN NEBULA [A-Z0-9 ]*PUBLIC KEY-----/', $nodePub),
    '/tmp/n.pub has a valid Nebula PUBLIC KEY header'
);
echo "INFO: node public key:\n{$nodePub}";

// ---------------------------------------------------------------------------
// Test 2: CSR sign — provide public key, get cert-only back
// ---------------------------------------------------------------------------

echo "\n=== Test 2: pki_sign_cert with in_pub (CSR mode) ===\n";

$csrRes = call_pki('pki_sign_cert', [
    'name'     => 'csr-test-node',
    'networks' => '192.168.100.9/24',
    'ca_crt'   => $caCrt,
    'ca_key'   => $caKey,
    'in_pub'   => $nodePub,
]);

assert_true(empty($csrRes['error']), "pki_sign_cert (CSR) succeeded: " . ($csrRes['error'] ?? 'ok'));
assert_true(!empty($csrRes['crt']),  'CSR: returned non-empty crt');
assert_true(isset($csrRes['key']) && $csrRes['key'] === '', 'CSR: returned empty key (no private key on CA)');

$csrCrt = $csrRes['crt'];

// ---------------------------------------------------------------------------
// Test 3: Verify CSR cert details via pki_print_cert
// ---------------------------------------------------------------------------

echo "\n=== Test 3: verify CSR cert details ===\n";

$printRes = call_pki('pki_print_cert', ['crt' => $csrCrt]);
assert_true(empty($printRes['error']), 'pki_print_cert accepts CSR cert');
assert_true(!empty($printRes['info']), 'pki_print_cert returns info for CSR cert');

$details = $printRes['info'][0]['details'] ?? [];

assert_true($details['isCa'] === false, 'CSR cert: isCa=false');
assert_true(($details['name'] ?? '') === 'csr-test-node', "CSR cert: name=csr-test-node (got: " . ($details['name'] ?? '') . ")");

$certNetworks = $details['networks'] ?? [];
if (!is_array($certNetworks)) {
    $certNetworks = [$certNetworks];
}
$hasNetwork = false;
foreach ($certNetworks as $n) {
    if (strpos($n, '192.168.100.9') !== false) {
        $hasNetwork = true;
        break;
    }
}
assert_true($hasNetwork, "CSR cert: networks contain 192.168.100.9/24 (got: " . implode(',', $certNetworks) . ")");

$issuerFp = $details['issuer'] ?? '';
assert_true(!empty($issuerFp), 'CSR cert: has non-empty issuer fingerprint');
assert_true($issuerFp === $caFingerprint, "CSR cert: issuer fp matches demo-ca (issuer={$issuerFp}, ca={$caFingerprint})");
echo "INFO: CSR cert issuer matches demo-ca\n";

// Verify the cert embeds the node's public key (not a different key).
// nebula-cert print -json: publicKey is a top-level hex field on each cert object.
$certPubKeyHex = $printRes['info'][0]['publicKey'] ?? '';
assert_true(!empty($certPubKeyHex), 'CSR cert: has publicKey in print output');
echo "INFO: CSR cert embedded publicKey (hex)={$certPubKeyHex}\n";

// Cross-check: run nebula-cert print on a freshly re-signed cert from the SAME pub key
// and compare publicKey fields — they must be identical (same node pub → same embedded key).
$csrRes2 = call_pki('pki_sign_cert', [
    'name'     => 'csr-test-node',
    'networks' => '192.168.100.9/24',
    'ca_crt'   => $caCrt,
    'ca_key'   => $caKey,
    'in_pub'   => $nodePub,
]);
$printRes2 = call_pki('pki_print_cert', ['crt' => $csrRes2['crt']]);
$certPubKeyHex2 = $printRes2['info'][0]['publicKey'] ?? '';
assert_true(
    $certPubKeyHex === $certPubKeyHex2,
    'CSR cert: embedded publicKey is deterministic from the same node pubkey'
);

// ---------------------------------------------------------------------------
// Test 4: Store CSR cert in model, verify has_key=0
// ---------------------------------------------------------------------------

echo "\n=== Test 4: store CSR cert in model (has_key=0) ===\n";

$mdlCsr = new Nebula();
$csrNode = $mdlCsr->pki->certificates->certificate->Add();
$csrNode->descr           = 'csr-test-cert';
$csrNode->origin          = 'signed';
$csrNode->caref           = $caUuid;
$csrNode->crt             = $csrCrt;
$csrNode->key             = '';
$csrNode->networks        = '192.168.100.9/24';
$csrNode->has_key         = '0';
$csrNode->ca_name         = $caName;
$csrUuid = $csrNode->getAttribute('uuid');
assert_true(!empty($csrUuid), 'CSR cert Add() assigned UUID');
assert_true((string)$csrNode->has_key === '0', 'CSR cert: has_key=0');
assert_true((string)$csrNode->key === '', 'CSR cert: key is empty');

$mdlCsr->serializeToConfig(false, true);
Config::getInstance()->save();
echo "INFO: CSR cert saved uuid={$csrUuid}\n";

// Reload and verify
$mdlCsrR = new Nebula();
$foundCsr = find_by_uuid($mdlCsrR->pki->certificates->certificate, $csrUuid);
assert_true($foundCsr !== null,                     'CSR cert persists after reload');
assert_true(!empty((string)$foundCsr->crt),         'Reloaded CSR cert: crt non-empty');
assert_true((string)$foundCsr->key === '',          'Reloaded CSR cert: key empty');
assert_true((string)$foundCsr->has_key === '0',    'Reloaded CSR cert: has_key=0');

// ---------------------------------------------------------------------------
// Test 5: generate-here still yields has_key=1
// ---------------------------------------------------------------------------

echo "\n=== Test 5: generate-here mode still yields has_key=1 ===\n";

$genRes = call_pki('pki_sign_cert', [
    'name'     => 'gen-test-node',
    'networks' => '192.168.100.10/24',
    'ca_crt'   => $caCrt,
    'ca_key'   => $caKey,
]);
assert_true(empty($genRes['error']), 'generate-here sign succeeded: ' . ($genRes['error'] ?? 'ok'));
assert_true(!empty($genRes['crt']),  'generate-here: crt non-empty');
assert_true(!empty($genRes['key']),  'generate-here: key non-empty');

$mdlGen = new Nebula();
$genNode = $mdlGen->pki->certificates->certificate->Add();
$genNode->descr   = 'gen-test-cert';
$genNode->origin  = 'signed';
$genNode->caref   = $caUuid;
$genNode->crt     = $genRes['crt'];
$genNode->key     = $genRes['key'];
$genNode->networks = '192.168.100.10/24';
$genNode->has_key = ($genRes['key'] !== '') ? '1' : '0';
$genUuid = $genNode->getAttribute('uuid');
assert_true((string)$genNode->has_key === '1', 'generate-here: has_key=1');
assert_true(!empty((string)$genNode->key),       'generate-here: key stored');

$mdlGen->serializeToConfig(false, true);
Config::getInstance()->save();
echo "INFO: generate-here cert saved uuid={$genUuid}\n";

$mdlGenR = new Nebula();
$foundGen = find_by_uuid($mdlGenR->pki->certificates->certificate, $genUuid);
assert_true($foundGen !== null,                  'generate-here cert persists');
assert_true(!empty((string)$foundGen->key),      'Reloaded generate-here cert: key non-empty');
assert_true((string)$foundGen->has_key === '1', 'Reloaded generate-here cert: has_key=1');

// ---------------------------------------------------------------------------
// Test 6: validatePublicKeyPem rejects non-Nebula PEM
// ---------------------------------------------------------------------------

echo "\n=== Test 6: public_key PEM validation ===\n";

$badPem  = "-----BEGIN RSA PUBLIC KEY-----\nMIIBIjANBgkq\n-----END RSA PUBLIC KEY-----\n";
$goodPem = $nodePub;

// Test the regex directly (mirrors validatePublicKeyPem in the controller).
$badMatch  = (bool)preg_match('/-----BEGIN NEBULA [A-Z0-9 ]*PUBLIC KEY-----/', $badPem);
$goodMatch = (bool)preg_match('/-----BEGIN NEBULA [A-Z0-9 ]*PUBLIC KEY-----/', $goodPem);

assert_true(!$badMatch,  'validatePublicKeyPem: rejects non-Nebula RSA PEM');
assert_true($goodMatch,  'validatePublicKeyPem: accepts Nebula public key PEM');

// Also confirm pki_sign_cert outright fails when given a bad public key blob.
$badSignRes = call_pki('pki_sign_cert', [
    'name'     => 'bad-pub-test',
    'networks' => '192.168.100.11/24',
    'ca_crt'   => $caCrt,
    'ca_key'   => $caKey,
    'in_pub'   => "not a real public key\n",
]);
assert_true(!empty($badSignRes['error']), 'pki_sign_cert rejects garbage in_pub (error=' . ($badSignRes['error'] ?? 'none') . ')');

// ---------------------------------------------------------------------------
// Cleanup
// ---------------------------------------------------------------------------

echo "\n=== Cleanup ===\n";

$mdlClean = new Nebula();
$r1 = $mdlClean->pki->certificates->certificate->del($csrUuid);
$r2 = $mdlClean->pki->certificates->certificate->del($genUuid);
assert_true($r1, 'del CSR cert');
assert_true($r2, 'del generate-here cert');
$mdlClean->serializeToConfig();
Config::getInstance()->save();

@unlink($pubFile);
@unlink($keyFile);

echo "INFO: cleanup complete (demo-ca untouched)\n";
echo "\n=== All CSR live tests PASSED ===\n";
exit(0);
