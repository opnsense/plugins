#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
 * All rights reserved.
 *
 * Live verification of encrypted-CA support (INFRA-163):
 *   - generate an encrypted CA via AuthorityController::generateAction(passphrase)
 *     → key PEM is ENCRYPTED, key_encrypted=1, can_sign=1, fingerprint populated.
 *   - sign a host under it WITH the right passphrase → success.
 *   - sign WITHOUT a passphrase → validations.passphrase "passphrase is required".
 *   - sign with the WRONG passphrase → validations.passphrase "invalid CA passphrase".
 *   - generate an UNencrypted CA → key_encrypted=0, signs with no passphrase.
 *   - confirm the passphrase is NEVER written to config.xml.
 *
 * Drives the REAL AuthorityController / CertificateController action methods
 * against the live config + configd (genuine nebula-cert), via a FakeRequest that
 * scripts getPost() params (same pattern as test_pki_semantics_live.php).
 *
 * Run on the OPNsense guest after `make install`:
 *   php /usr/plugins/net/nebula/tools/test_encrypted_ca_live.php
 *
 * Exit 0 = all assertions passed; exit 1 = failure (message to stderr).
 * The script cleans up every authority/cert it creates.
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

use OPNsense\Core\Config;
use OPNsense\Nebula\Nebula;
use OPNsense\Nebula\Api\AuthorityController;
use OPNsense\Nebula\Api\CertificateController;

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

class FakeRequest extends \OPNsense\Mvc\Request
{
    public array $post = [];
    public function isPost(): bool
    {
        return true;
    }
    public function getPost(?string $name = null, ?string $filter = null, mixed $default = null): mixed
    {
        return $this->post[$name] ?? $default;
    }
}

class TestAuthorityController extends AuthorityController
{
    public function __construct()
    {
    }
    public function setPost(array $p): void
    {
        $r = new FakeRequest();
        $r->post = $p;
        $this->request = $r;
    }
    public function getModel()
    {
        return parent::getModel();
    }
}

class TestCertificateController extends CertificateController
{
    public function __construct()
    {
    }
    public function setPost(array $p): void
    {
        $r = new FakeRequest();
        $r->post = $p;
        $this->request = $r;
    }
    public function getModel()
    {
        return parent::getModel();
    }
}

function reload_authority(string $uuid)
{
    $mdl = new Nebula();
    foreach ($mdl->pki->authorities->authority->iterateItems() as $n) {
        if ($n->getAttribute('uuid') === $uuid) {
            return $n;
        }
    }
    return null;
}

$created_authorities = [];
$created_certs       = [];

function cleanup(): void
{
    global $created_authorities, $created_certs;
    $mdl = new Nebula();
    foreach ($created_certs as $u) {
        $mdl->pki->certificates->certificate->del($u);
    }
    foreach ($created_authorities as $u) {
        $mdl->pki->authorities->authority->del($u);
    }
    $mdl->serializeToConfig();
    Config::getInstance()->save();
}

/**
 * Self-healing sweep: remove any items left by an earlier interrupted run of this
 * script (matched by the fixed descr names it uses), so re-runs start clean.
 */
function sweep_by_descr(): void
{
    $certNames = ['enc-host-ok', 'enc-host-nopass', 'enc-host-wrong', 'plain-host'];
    $caNames   = ['enc-ca', 'plain-ca'];
    $mdl = new Nebula();
    $n = 0;
    foreach ($mdl->pki->certificates->certificate->iterateItems() as $it) {
        if (in_array((string)$it->descr, $certNames, true)) {
            $mdl->pki->certificates->certificate->del($it->getAttribute('uuid'));
            $n++;
        }
    }
    foreach ($mdl->pki->authorities->authority->iterateItems() as $it) {
        if (in_array((string)$it->descr, $caNames, true)) {
            $mdl->pki->authorities->authority->del($it->getAttribute('uuid'));
            $n++;
        }
    }
    if ($n > 0) {
        $mdl->serializeToConfig();
        Config::getInstance()->save();
        echo "INFO: swept {$n} leftover item(s) from a prior run\n";
    }
}

$PASS = 'p1-secret';

if (getenv('NEB_PHASE') !== '2') {
// ===== PHASE 1: generate the CAs (single process) ==========================
// Sweep any leftovers from a prior interrupted run first.
sweep_by_descr();

// The certificate `caref` ModelRelationField caches its option list process-
// statically the first time it is validated.  If we sign in the same process
// that created the CA, the cache (primed before our Add()) won't see the new CA
// and the assignment is dropped ("Option [] not in list.").  So phase 1 only
// generates + asserts CA fields, then re-execs a fresh process (phase 2) for all
// signing — exactly as test_pki_semantics_live.php does.

// ---------------------------------------------------------------------------
echo "\n=== 1. Generate an ENCRYPTED CA (passphrase) → encrypted key, can_sign=1 ===\n";
$ac = new TestAuthorityController();
$ac->setPost([
    'name' => 'enc-ca', 'descr' => 'enc-ca', 'curve' => '25519',
    'duration_days' => 365, 'networks' => '10.66.0.0/24',
    'passphrase' => $PASS,
]);
$res = $ac->generateAction();
assert_true(($res['result'] ?? '') === 'saved', "encrypted CA generate succeeded (" . json_encode($res) . ")");
$enc_uuid = $res['uuid'];
$enc_node = reload_authority($enc_uuid);
assert_true(stripos((string)$enc_node->key, 'ENCRYPTED') !== false, "stored CA key PEM is ENCRYPTED");
assert_true((string)$enc_node->key_encrypted === '1', "key_encrypted=1");
assert_true((string)$enc_node->has_key       === '1', "has_key=1");
assert_true((string)$enc_node->can_sign      === '1', "can_sign=1 (encrypted CA is signable)");
assert_true((string)$enc_node->fingerprint   !== '',  "fingerprint populated");

// The passphrase must NEVER be persisted anywhere in the stored authority node.
$blob = json_encode([
    'descr' => (string)$enc_node->descr, 'crt' => (string)$enc_node->crt,
    'key' => (string)$enc_node->key, 'cn' => (string)$enc_node->cn,
]);
assert_true(strpos($blob, $PASS) === false, "passphrase is NOT stored in the authority node fields");

// ---------------------------------------------------------------------------
echo "\n=== 2. Generate an UNencrypted CA → key_encrypted=0 ===\n";
$ac2 = new TestAuthorityController();
$ac2->setPost([
    'name' => 'plain-ca', 'descr' => 'plain-ca', 'curve' => '25519',
    'duration_days' => 365, 'networks' => '10.77.0.0/24',
]);
$res2 = $ac2->generateAction();
assert_true(($res2['result'] ?? '') === 'saved', "unencrypted CA generate succeeded (" . json_encode($res2) . ")");
$plain_uuid = $res2['uuid'];
$plain_node = reload_authority($plain_uuid);
assert_true(stripos((string)$plain_node->key, 'ENCRYPTED') === false, "plain CA key PEM is NOT encrypted");
assert_true((string)$plain_node->key_encrypted === '0', "plain CA key_encrypted=0");
assert_true((string)$plain_node->can_sign      === '1', "plain CA can_sign=1");

// Re-exec for phase 2 (fresh process → real caref cache).  The child owns cleanup.
$env = ['NEB_PHASE' => '2', 'NEB_ENC' => $enc_uuid, 'NEB_PLAIN' => $plain_uuid, 'NEB_PASS' => $PASS];
$envPrefix = '';
foreach ($env as $k => $v) {
    $envPrefix .= $k . '=' . escapeshellarg($v) . ' ';
}
echo "\n--- re-exec for phase 2 (fresh process; real caref cache) ---\n";
passthru("$envPrefix /usr/local/bin/php " . escapeshellarg(__FILE__), $childRc);
exit($childRc);
}

// ===== PHASE 2 (fresh process): signing + config.xml check + cleanup =======
$enc_uuid   = getenv('NEB_ENC');
$plain_uuid = getenv('NEB_PLAIN');
$PASS       = getenv('NEB_PASS');
$created_authorities = [$enc_uuid, $plain_uuid];

// ===========================================================================
echo "\n=== 3. Sign a host under the encrypted CA WITH the right passphrase → success ===\n";
$cc = new TestCertificateController();
$cc->setPost([
    'descr' => 'enc-host-ok', 'caref' => $enc_uuid, 'name' => 'enc-host-ok',
    'networks' => '10.66.0.7/24', 'passphrase' => $PASS,
]);
$res3 = $cc->signAction();
assert_true(($res3['result'] ?? '') === 'saved', "sign with right passphrase succeeded (" . json_encode($res3) . ")");
$created_certs[] = $res3['uuid'];

// ===========================================================================
echo "\n=== 4. Sign WITHOUT a passphrase → validation: passphrase required ===\n";
$cc2 = new TestCertificateController();
$cc2->setPost([
    'descr' => 'enc-host-nopass', 'caref' => $enc_uuid, 'name' => 'enc-host-nopass',
    'networks' => '10.66.0.8/24',
]);
$res4 = $cc2->signAction();
assert_true(($res4['result'] ?? '') === 'failed', "sign without passphrase rejected");
assert_true(
    isset($res4['validations']['passphrase']) && stripos($res4['validations']['passphrase'], 'required') !== false,
    "rejection asks for a passphrase: " . ($res4['validations']['passphrase'] ?? '')
);

// ===========================================================================
echo "\n=== 5. Sign with the WRONG passphrase → validation: invalid CA passphrase ===\n";
$cc3 = new TestCertificateController();
$cc3->setPost([
    'descr' => 'enc-host-wrong', 'caref' => $enc_uuid, 'name' => 'enc-host-wrong',
    'networks' => '10.66.0.9/24', 'passphrase' => 'totally-wrong',
]);
$res5 = $cc3->signAction();
assert_true(($res5['result'] ?? '') === 'failed', "sign with wrong passphrase rejected");
assert_true(
    isset($res5['validations']['passphrase']) && stripos($res5['validations']['passphrase'], 'invalid CA passphrase') !== false,
    "rejection maps nebula error to 'invalid CA passphrase': " . ($res5['validations']['passphrase'] ?? '')
);

// ===========================================================================
echo "\n=== 6. Sign under the UNencrypted CA with no passphrase → success ===\n";
$cc4 = new TestCertificateController();
$cc4->setPost([
    'descr' => 'plain-host', 'caref' => $plain_uuid, 'name' => 'plain-host',
    'networks' => '10.77.0.5/24',
]);
$res6 = $cc4->signAction();
assert_true(($res6['result'] ?? '') === 'saved', "sign under plain CA with no passphrase succeeded (" . json_encode($res6) . ")");
$created_certs[] = $res6['uuid'];

// ===========================================================================
echo "\n=== 7. Passphrase absent from on-disk config.xml ===\n";
$cfgRaw = @file_get_contents('/conf/config.xml');
assert_true($cfgRaw !== false, "read /conf/config.xml");
assert_true(strpos($cfgRaw, $PASS) === false, "passphrase string does NOT appear anywhere in config.xml");

// ===========================================================================
echo "\n=== Cleanup ===\n";
cleanup();
echo "INFO: cleanup complete\n";
echo "\n=== All encrypted-CA live checks PASSED ===\n";
exit(0);
