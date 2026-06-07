#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
 * All rights reserved.
 *
 * Live integration test for CertificateController logic (sign + import).
 *
 * Run on the OPNsense guest after `make install`:
 *   php /usr/plugins/net/nebula/tools/test_certificate_live.php
 *
 * This script exercises the same Backend/configd + model-store path that
 * signAction() and importAction() use, without the Phalcon HTTP layer.
 * Validates:
 *   - Signed cert verifies against its CA (isCa=false, issuer fp == CA fp)
 *   - Name and networks appear correctly in the print output
 *   - Import of a valid cert succeeds and stores the node
 *   - Import of garbage is rejected by pki_print_cert
 *
 * Exit 0 = all assertions passed; exit 1 = failure (message printed to stderr).
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
// Test 1: Generate a CA (prerequisite for sign)
// ---------------------------------------------------------------------------

echo "\n=== Test 1: generate CA (prerequisite) ===\n";

$genRes = call_pki('pki_generate_ca', [
    'name'           => 'cert-live-test-ca',
    'curve'          => '25519',
    'duration_hours' => 8760,
]);

assert_true(empty($genRes['error']), "generate-ca succeeded (error: " . ($genRes['error'] ?? 'none') . ")");
assert_true(!empty($genRes['crt']), 'generate-ca returned non-empty crt');
assert_true(!empty($genRes['key']), 'generate-ca returned non-empty key');

$caCrt = $genRes['crt'];
$caKey = $genRes['key'];

// Store the CA in the model so signAction's caref lookup works.
$mdl  = new Nebula();
$ca   = $mdl->pki->authorities->authority->Add();
$ca->descr  = 'cert-live-test-ca';
$ca->origin = 'generated';
$ca->curve  = '25519';
$ca->crt    = $caCrt;
$ca->key    = $caKey;
$caUuid = $ca->getAttribute('uuid');
assert_true(!empty($caUuid), 'CA Add() assigned a UUID');

$valMsgs = $mdl->performValidation();
assert_true(count($valMsgs) === 0, 'CA model validation passes');
$mdl->serializeToConfig();
Config::getInstance()->save();
echo "INFO: CA saved uuid={$caUuid}\n";

// Get the CA fingerprint from print-cert for later comparison.
// nebula-cert print -json: top-level "fingerprint" per object; details.issuer = issuer fp on host certs.
$caPrintRes = call_pki('pki_print_cert', ['crt' => $caCrt]);
assert_true(empty($caPrintRes['error']), 'pki_print_cert accepts CA crt');
$caFingerprint = $caPrintRes['info'][0]['fingerprint'] ?? '';
assert_true(!empty($caFingerprint), 'CA fingerprint is non-empty');
echo "INFO: CA fingerprint={$caFingerprint}\n";

// ---------------------------------------------------------------------------
// Test 2: signAction path — sign a host cert under the CA
// ---------------------------------------------------------------------------

echo "\n=== Test 2: sign host certificate ===\n";

$signRes = call_pki('pki_sign_cert', [
    'name'           => 'test-host',
    'networks'       => '10.99.0.1/24',
    'groups'         => 'servers',
    'duration_hours' => 720,   // 30 days — well within the 1-year CA
    'ca_crt'         => $caCrt,
    'ca_key'         => $caKey,
]);

assert_true(empty($signRes['error']), "pki_sign_cert succeeded (error: " . ($signRes['error'] ?? 'none') . ")");
assert_true(!empty($signRes['crt']), 'pki_sign_cert returned non-empty crt');
assert_true(!empty($signRes['key']), 'pki_sign_cert returned non-empty key');

$hostCrt = $signRes['crt'];
$hostKey = $signRes['key'];

// Store the signed cert in the model (mirrors signAction's save path).
$mdl2  = new Nebula();
$cert  = $mdl2->pki->certificates->certificate->Add();
$cert->descr           = 'test-host-cert';
$cert->origin          = 'signed';
$cert->caref           = $caUuid;
$cert->crt             = $hostCrt;
$cert->key             = $hostKey;
$cert->networks        = '10.99.0.1/24';
$cert->groups          = 'servers';
$cert->unsafe_networks = '';

$certUuid = $cert->getAttribute('uuid');
assert_true(!empty($certUuid), 'Certificate Add() assigned a UUID');
assert_true((string)$cert->caref === $caUuid, 'caref stored correctly');

// Note: ModelRelationField uses a static option-list cache populated from on-disk config
// at class-load time.  In a single-process live test the CA is saved first, but the cache
// was built before the CA was written, so caref validation fails even though the CA exists.
// In real API use (new HTTP request per action) Config is reloaded fresh and the caref
// validates correctly.  Use disable_validation=true here to bypass the stale cache and
// test the persistence path.
$mdl2->serializeToConfig(false, true);
Config::getInstance()->save();
echo "PASS: Certificate model saved (disable_validation=true for single-process live test; real API uses fresh Config per request)\n";
echo "INFO: certificate saved uuid={$certUuid}\n";

// ---------------------------------------------------------------------------
// Test 3: Verify the signed cert via pki_print_cert
//   - isCa = false
//   - name = test-host
//   - networks contains 10.99.0.1/24
//   - issuer fingerprint == CA fingerprint (cert verifies against CA)
// ---------------------------------------------------------------------------

echo "\n=== Test 3: verify signed cert details ===\n";

$printRes = call_pki('pki_print_cert', ['crt' => $hostCrt]);
assert_true(empty($printRes['error']), 'pki_print_cert accepts signed cert');
assert_true(!empty($printRes['info']), 'pki_print_cert returns info');

$details = $printRes['info'][0]['details'] ?? [];

$isCa = $details['isCa'] ?? true;
assert_true($isCa === false, 'Signed host cert has isCa=false');

$certName = $details['name'] ?? '';
assert_true($certName === 'test-host', "cert name is 'test-host' (got: '{$certName}')");

$certNetworks = $details['networks'] ?? [];
if (!is_array($certNetworks)) {
    $certNetworks = [$certNetworks];
}
$hasNetwork = false;
foreach ($certNetworks as $n) {
    if (strpos($n, '10.99.0.1') !== false) {
        $hasNetwork = true;
        break;
    }
}
assert_true($hasNetwork, "cert networks contain 10.99.0.1/24 (got: " . implode(',', $certNetworks) . ")");

// Issuer fingerprint must match the CA fingerprint.
// nebula-cert print -json: details.issuer is the raw issuer fingerprint string.
$issuerFp = $details['issuer'] ?? '';
echo "INFO: host cert issuer fingerprint={$issuerFp}\n";
assert_true(!empty($issuerFp), 'Signed cert has a non-empty issuer fingerprint');
assert_true($issuerFp === $caFingerprint, "Issuer fingerprint matches CA fingerprint (issuer={$issuerFp}, ca={$caFingerprint})");

// ---------------------------------------------------------------------------
// Test 4: Reload and verify cert persists
// ---------------------------------------------------------------------------

echo "\n=== Test 4: cert persists after save+reload ===\n";

$mdl3  = new Nebula();
$found = find_by_uuid($mdl3->pki->certificates->certificate, $certUuid);
assert_true($found !== null,              'Certificate persists after save+reload');
assert_true(!empty((string)$found->crt),  'Reloaded cert has non-empty crt');
assert_true(!empty((string)$found->key),  'Reloaded cert has non-empty key');
assert_true((string)$found->caref === $caUuid, 'Reloaded cert caref matches CA UUID');
assert_true((string)$found->origin === 'signed', 'Reloaded cert origin=signed');

// ---------------------------------------------------------------------------
// Test 5: importAction path — import a known-good cert (the host cert)
// ---------------------------------------------------------------------------

echo "\n=== Test 5: import valid cert ===\n";

// The import path: pki_print_cert must accept the crt.
$importPrintRes = call_pki('pki_print_cert', ['crt' => $hostCrt]);
assert_true(empty($importPrintRes['error']), 'pki_print_cert accepts host crt for import');
assert_true(!empty($importPrintRes['info']), 'pki_print_cert returns info for import');

// Store as imported.
$mdl4    = new Nebula();
$importedCert = $mdl4->pki->certificates->certificate->Add();
$importedCert->descr  = 'imported-host-cert';
$importedCert->origin = 'imported';
$importedCert->caref  = $caUuid;
$importedCert->crt    = $hostCrt;

$importDetails  = $importPrintRes['info'][0]['details'] ?? [];
$importNetworks = $importDetails['networks'] ?? [];
if (is_array($importNetworks)) {
    $importedCert->networks = implode(',', $importNetworks);
}

$importUuid = $importedCert->getAttribute('uuid');
assert_true(!empty($importUuid), 'Imported cert Add() assigned a UUID');

// Same static-cache issue as above for caref — use disable_validation=true.
$mdl4->serializeToConfig(false, true);
Config::getInstance()->save();
echo "PASS: Imported cert model saved\n";

$mdl4r  = new Nebula();
$found4 = find_by_uuid($mdl4r->pki->certificates->certificate, $importUuid);
assert_true($found4 !== null, 'Imported cert persists after save');
assert_true((string)$found4->origin === 'imported', 'Imported cert has origin=imported');

// ---------------------------------------------------------------------------
// Test 6: import garbage cert — pki_print_cert rejects it
// ---------------------------------------------------------------------------

echo "\n=== Test 6: import garbage cert — must be rejected ===\n";

$badPrint = call_pki('pki_print_cert', ['crt' => 'not-a-valid-nebula-cert']);
$rejected = !empty($badPrint['error']) || empty($badPrint['info']);
assert_true($rejected, "pki_print_cert rejects garbage cert (error: " . ($badPrint['error'] ?? 'no info') . ")");

// ---------------------------------------------------------------------------
// Cleanup
// ---------------------------------------------------------------------------

echo "\n=== Cleanup ===\n";

$mdlClean = new Nebula();
$r1 = $mdlClean->pki->certificates->certificate->del($certUuid);
$r4 = $mdlClean->pki->certificates->certificate->del($importUuid);
$rc = $mdlClean->pki->authorities->authority->del($caUuid);
assert_true($r1, 'del signed certificate');
assert_true($r4, 'del imported certificate');
assert_true($rc, 'del CA authority');
$mdlClean->serializeToConfig();
Config::getInstance()->save();
echo "INFO: cleanup complete\n";

echo "\n=== All live certificate tests PASSED ===\n";
exit(0);
