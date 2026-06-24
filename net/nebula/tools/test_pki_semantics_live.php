#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
 * All rights reserved.
 *
 * Live verification of the PKI-semantics fixes (can_sign vs has_key, accept
 * encrypted CA keys, reject non-CA on CA import, issuer-derived CA name, curve
 * column population, signer-dropdown filter data).
 *
 * Drives the REAL AuthorityController / CertificateController action methods
 * against the live config + configd (genuine nebula-cert), by subclassing each
 * controller to inject a fake request whose getPost() returns scripted params.
 *
 * Run on the OPNsense guest after `make install`:
 *   php /usr/plugins/net/nebula/tools/test_pki_semantics_live.php
 *
 * Exit 0 = all assertions passed; exit 1 = failure (message printed to stderr).
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

use OPNsense\Core\Backend;
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

// A stand-in for the request that extends the real typed Request so it can be
// assigned to the controller's typed $request property; scripts POST params.
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

// Test subclasses that bypass the Phalcon DI/HTTP layer: we set $this->request
// directly and stub the validator-trigger so the base controller's
// performValidation()/serialize path runs against the live model.
class TestAuthorityController extends AuthorityController
{
    public function __construct()
    {
        // do not call parent::__construct (needs Phalcon DI)
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

function call_pki(string $action, array $params): array
{
    $b64 = base64_encode(json_encode($params));
    $out = (new Backend())->configdpRun("nebula {$action}", [$b64]);
    $res = json_decode($out, true);
    return is_array($res) ? $res : ['error' => 'non-JSON'];
}

// Read a fresh node from the live config by uuid.
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
function reload_cert(string $uuid)
{
    $mdl = new Nebula();
    foreach ($mdl->pki->certificates->certificate->iterateItems() as $n) {
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

if (getenv('NEB_PHASE') !== '2') {
// ===== PHASE 1: PEM generation + tests 1-5 (single process) ================
// Build the PEM artifacts we need by calling nebula-cert directly.
// NB: $tmp is intentionally NOT cleaned up at phase-1 shutdown — phase 2 (a fresh
// re-exec, see below) reuses these PEMs and owns the final cleanup of $tmp.
$tmp = rtrim(shell_exec('mktemp -d'), "\n");

// demo-ca (signable), host signed by demo-ca, a cert-only "foo" CA, an encrypted CA.
shell_exec("/usr/local/bin/nebula-cert ca -name demo-ca -networks 10.44.0.0/24 -out-crt $tmp/demo.crt -out-key $tmp/demo.key -duration 8760h");
shell_exec("/usr/local/bin/nebula-cert ca -name foo -networks 10.99.0.0/24 -out-crt $tmp/foo.crt -out-key $tmp/foo.key -duration 8760h");
shell_exec("/usr/local/bin/nebula-cert sign -ca-crt $tmp/demo.crt -ca-key $tmp/demo.key -name host-demo -networks 10.44.0.7/24 -out-crt $tmp/host.crt -out-key $tmp/host.key");
// Encrypted CA key via a pty wrapper.
$pty = <<<PY
import pty,os,time
pid,fd=pty.fork()
if pid==0:
    os.execvp("/usr/local/bin/nebula-cert",["nebula-cert","ca","-name","enc-ca","-networks","10.55.0.0/24","-encrypt","-out-crt","$tmp/enc.crt","-out-key","$tmp/enc.key"])
else:
    buf=b"";sent=0
    while True:
        try:d=os.read(fd,1024)
        except OSError:break
        if not d:break
        buf+=d
        if buf.lower().count(b"passphrase")>sent:
            os.write(fd,b"secret123\\n");sent+=1;time.sleep(0.3)
    os.waitpid(pid,0)
PY;
file_put_contents("$tmp/encgen.py", $pty);
shell_exec("python3 $tmp/encgen.py 2>/dev/null");

$demo_crt = file_get_contents("$tmp/demo.crt");
$demo_key = file_get_contents("$tmp/demo.key");
$foo_crt  = file_get_contents("$tmp/foo.crt");
$host_crt = file_get_contents("$tmp/host.crt");
$host_key = file_get_contents("$tmp/host.key");
$enc_crt  = file_get_contents("$tmp/enc.crt");
$enc_key  = file_get_contents("$tmp/enc.key");

assert_true($enc_key !== false && stripos($enc_key, 'ENCRYPTED') !== false, "crafted an encrypted NEBULA key PEM (header contains ENCRYPTED)");

// demo-ca fingerprint (from print) — for issuer-resolution checks.
$demoPrint = call_pki('pki_print_cert', ['crt' => $demo_crt]);
$demo_fp   = $demoPrint['info'][0]['fingerprint'] ?? '';
assert_true($demo_fp !== '', "obtained demo-ca fingerprint: " . substr($demo_fp, 0, 12) . "…");

// ===========================================================================
echo "\n=== 1. Cert-only CA import (foo: crt only, no key) ===\n";
$ac = new TestAuthorityController();
$ac->setPost(['descr' => 'foo', 'crt' => $foo_crt, 'key' => '']);
$res = $ac->importAction();
assert_true(($res['result'] ?? '') === 'saved', "cert-only CA import accepted (" . json_encode($res) . ")");
$foo_uuid = $res['uuid'];
$created_authorities[] = $foo_uuid;
$foo_node = reload_authority($foo_uuid);
assert_true((string)$foo_node->has_key  === '0', "cert-only CA has_key=0");
assert_true((string)$foo_node->can_sign === '0', "cert-only CA can_sign=0");
assert_true((string)$foo_node->is_ca    === '1', "cert-only CA is_ca=1");
assert_true((string)$foo_node->curve    !== '',  "cert-only CA curve populated: " . (string)$foo_node->curve);

// ===========================================================================
echo "\n=== 2. Import a non-CA cert as a CA → rejected (isCa=false) ===\n";
$ac2 = new TestAuthorityController();
$ac2->setPost(['descr' => 'should-fail', 'crt' => $host_crt, 'key' => '']);
$res2 = $ac2->importAction();
assert_true(($res2['result'] ?? '') === 'failed', "non-CA import rejected");
assert_true(isset($res2['validations']['crt']) && stripos($res2['validations']['crt'], 'not a CA') !== false,
    "rejection message mentions isCa=false: " . ($res2['validations']['crt'] ?? ''));

// ===========================================================================
echo "\n=== 3. Import an ENCRYPTED CA key → accepted, has_key=1, key_encrypted=1, can_sign=1 ===\n";
$ac3 = new TestAuthorityController();
$ac3->setPost(['descr' => 'enc-ca', 'crt' => $enc_crt, 'key' => $enc_key]);
$res3 = $ac3->importAction();
assert_true(($res3['result'] ?? '') === 'saved', "encrypted CA import accepted (" . json_encode($res3) . ")");
$enc_uuid = $res3['uuid'];
$created_authorities[] = $enc_uuid;
$enc_node = reload_authority($enc_uuid);
assert_true((string)$enc_node->has_key       === '1', "encrypted CA has_key=1 (key IS stored → download button shows)");
assert_true((string)$enc_node->key_encrypted === '1', "encrypted CA key_encrypted=1 (Sign dialog prompts for passphrase)");
assert_true((string)$enc_node->can_sign      === '1', "encrypted CA can_sign=1 (signable with a passphrase, INFRA-163)");

// ===========================================================================
echo "\n=== 4. Generate a proper CA (demo-ca) → can_sign=1 ===\n";
$ac4 = new TestAuthorityController();
$ac4->setPost([
    'name' => 'demo-ca', 'descr' => 'demo-ca', 'curve' => '25519',
    'duration_days' => 365, 'networks' => '10.44.0.0/24',
]);
$res4 = $ac4->generateAction();
assert_true(($res4['result'] ?? '') === 'saved', "demo-ca generate succeeded (" . json_encode($res4) . ")");
$demo_uuid = $res4['uuid'];
$created_authorities[] = $demo_uuid;
$demo_node = reload_authority($demo_uuid);
assert_true((string)$demo_node->can_sign === '1', "generated demo-ca can_sign=1");
assert_true((string)$demo_node->has_key  === '1', "generated demo-ca has_key=1");
$demo_gen_fp = (string)$demo_node->fingerprint;
assert_true($demo_gen_fp !== '', "generated demo-ca fingerprint populated: " . substr($demo_gen_fp, 0, 12) . "…");

// ===========================================================================
echo "\n=== 5. searchItemAction returns can_sign + fingerprint + curve (dropdown data) ===\n";
$searchAc = new TestAuthorityController();
$searchAc->setPost([]);
$rows = $searchAc->searchItemAction()['rows'] ?? [];
$byUuid = [];
foreach ($rows as $r) {
    $byUuid[$r['uuid']] = $r;
}
assert_true(isset($byUuid[$demo_uuid]['can_sign']), "searchItem row has can_sign field");
assert_true(isset($byUuid[$demo_uuid]['fingerprint']), "searchItem row has fingerprint field");
assert_true(isset($byUuid[$demo_uuid]['curve']), "searchItem row has curve field");
assert_true(($byUuid[$demo_uuid]['can_sign'] ?? '') === '1', "demo-ca row can_sign=1 (would appear in dropdown)");
assert_true(($byUuid[$foo_uuid]['can_sign'] ?? '') === '0', "foo row can_sign=0 (filtered OUT of dropdown)");
assert_true(($byUuid[$enc_uuid]['can_sign'] ?? '') === '1', "enc-ca row can_sign=1 (IN the dropdown; passphrase prompted)");
assert_true(isset($byUuid[$enc_uuid]['key_encrypted']), "searchItem row has key_encrypted field");
assert_true(($byUuid[$enc_uuid]['key_encrypted'] ?? '') === '1', "enc-ca row key_encrypted=1 (drives Sign passphrase prompt)");
// Confirm no key/crt leaks into grid JSON.
assert_true(!isset($byUuid[$demo_uuid]['key']) && !isset($byUuid[$demo_uuid]['crt']), "searchItem strips key+crt PEM");

// ---------------------------------------------------------------------------
// End of phase 1.  Tests 6-9 touch the certificate `caref` ModelRelationField,
// whose option list is cached process-statically (keyed by source/filter) the
// first time it is validated.  In this single long-lived harness that cache was
// primed before the CAs above were saved, so a caref assignment here would be
// silently dropped — a harness-only artifact (each real GUI request is a fresh
// PHP process where the CA is always visible).  To reproduce real behaviour we
// re-exec this script as a fresh process for phase 2, passing the saved
// uuids/fingerprints + the $tmp PEM dir via env.  The child owns final cleanup.
// ---------------------------------------------------------------------------
    $env = [
        'NEB_PHASE'   => '2',
        'NEB_TMP'     => $tmp,
        'NEB_DEMO'    => $demo_uuid,
        'NEB_DEMO_FP' => $demo_gen_fp,
        'NEB_FILE_FP' => $demo_fp,
        'NEB_FOO'     => $foo_uuid,
        'NEB_ENC'     => $enc_uuid,
    ];
    $envPrefix = '';
    foreach ($env as $k => $v) {
        $envPrefix .= $k . '=' . escapeshellarg($v) . ' ';
    }
    echo "\n--- re-exec for phase 2 (fresh process; real caref cache) ---\n";
    $self = escapeshellarg(__FILE__);
    passthru("$envPrefix /usr/local/bin/php $self", $childRc);
    exit($childRc);
}

// ===== PHASE 2 (fresh process) ============================================
$tmp         = getenv('NEB_TMP');
$demo_uuid   = getenv('NEB_DEMO');
$demo_gen_fp = getenv('NEB_DEMO_FP');
$demo_fp     = getenv('NEB_FILE_FP');
$foo_uuid    = getenv('NEB_FOO');
$enc_uuid    = getenv('NEB_ENC');
$demo_crt = file_get_contents("$tmp/demo.crt");
$demo_key = file_get_contents("$tmp/demo.key");
$foo_crt  = file_get_contents("$tmp/foo.crt");
$host_crt = file_get_contents("$tmp/host.crt");
$host_key = file_get_contents("$tmp/host.key");
// Phase 2 owns cleanup of all items + the tmp dir.
$created_authorities = [$demo_uuid, $foo_uuid, $enc_uuid];
$created_certs       = [];
register_shutdown_function(fn() => shell_exec('rm -rf ' . escapeshellarg($tmp)));

// ===========================================================================
echo "\n=== 6. Sign a host cert under demo-ca → issuer = demo-ca fp; CA column resolves ===\n";
$cc = new TestCertificateController();
$cc->setPost([
    'descr' => 'signed-host', 'caref' => $demo_uuid, 'name' => 'signed-host',
    'networks' => '10.44.0.11/24',
]);
$res6 = $cc->signAction();
assert_true(($res6['result'] ?? '') === 'saved', "sign under demo-ca succeeded (" . json_encode($res6) . ")");
$signed_uuid = $res6['uuid'];
$created_certs[] = $signed_uuid;
$signed_node = reload_cert($signed_uuid);
assert_true((string)$signed_node->issuer === $demo_gen_fp, "signed cert issuer == demo-ca fingerprint");
assert_true((string)$signed_node->curve  !== '', "signed cert curve populated: " . (string)$signed_node->curve);
assert_true((string)$signed_node->has_key === '1', "signed cert (generate-here) has_key=1");

// ===========================================================================
echo "\n=== 7. Import a host cert (no caref) whose issuer IS demo-ca → CA resolves dynamically ===\n";
$cc2 = new TestCertificateController();
$cc2->setPost(['descr' => 'imported-host', 'crt' => $host_crt, 'key' => $host_key]);
$res7 = $cc2->importAction();
assert_true(($res7['result'] ?? '') === 'saved', "host import (no caref) succeeded (" . json_encode($res7) . ")");
$imp_uuid = $res7['uuid'];
$created_certs[] = $imp_uuid;
$imp_node = reload_cert($imp_uuid);
// host.crt was signed by the *file* demo-ca (demo_fp), which differs from the
// in-config generated demo-ca (demo_gen_fp). So its issuer is the file CA's fp.
assert_true((string)$imp_node->issuer === $demo_fp, "imported host issuer == signing-CA fingerprint");
assert_true((string)$imp_node->has_key === '1', "imported host (with key) has_key=1");

// Now resolve CA names via searchItemAction.
$searchCc = new TestCertificateController();
$searchCc->setPost([]);
$crows = $searchCc->searchItemAction()['rows'] ?? [];
$cByUuid = [];
foreach ($crows as $r) {
    $cByUuid[$r['uuid']] = $r;
}
// signed-host's issuer is the in-config demo-ca → resolves to "demo-ca".
assert_true(($cByUuid[$signed_uuid]['ca_name'] ?? '') === 'demo-ca',
    "signed-host CA column resolves to demo-ca (got: " . ($cByUuid[$signed_uuid]['ca_name'] ?? '') . ")");
// imported-host's issuer (file demo-ca) is NOT in the pool → "unknown: <fp>".
$imp_ca = $cByUuid[$imp_uuid]['ca_name'] ?? '';
assert_true(strpos($imp_ca, 'unknown: ') === 0 && strpos($imp_ca, $demo_fp) !== false,
    "imported-host (issuer not in pool) shows 'unknown: <fp>' (got: $imp_ca)");

// ===========================================================================
echo "\n=== 8. Now import the file demo-ca as a CA → imported-host auto-resolves ===\n";
$ac8 = new TestAuthorityController();
$ac8->setPost(['descr' => 'file-demo-ca', 'crt' => $demo_crt, 'key' => $demo_key]);
$res8 = $ac8->importAction();
assert_true(($res8['result'] ?? '') === 'saved', "file demo-ca import accepted");
$fileca_uuid = $res8['uuid'];
$created_authorities[] = $fileca_uuid;
$searchCc2 = new TestCertificateController();
$searchCc2->setPost([]);
$crows2 = $searchCc2->searchItemAction()['rows'] ?? [];
$resolved = '';
foreach ($crows2 as $r) {
    if ($r['uuid'] === $imp_uuid) {
        $resolved = $r['ca_name'];
    }
}
// The file demo-ca's embedded CN is "demo-ca" (we named both CAs demo-ca), and
// searchItemAction resolves via cn (fallback descr), so it correctly shows the
// CN "demo-ca" — the point is it is no longer "unknown:" once the CA is in pool.
assert_true($resolved === 'demo-ca' && strpos($resolved, 'unknown') === false,
    "imported-host CA auto-resolves to the CA's CN once imported (got: $resolved)");

// ===========================================================================
echo "\n=== 9. Duplicate CA import (same fingerprint) → rejected, tells user to delete first ===\n";
$ac9 = new TestAuthorityController();
$ac9->setPost(['descr' => 'foo-again', 'crt' => $foo_crt, 'key' => file_get_contents("$tmp/foo.key")]);
$res9 = $ac9->importAction();
assert_true(($res9['result'] ?? '') === 'failed', "duplicate CA import rejected");
assert_true(isset($res9['validations']['crt']) && stripos($res9['validations']['crt'], 'already imported') !== false,
    "duplicate rejection tells user to delete first: " . ($res9['validations']['crt'] ?? ''));

// ===========================================================================
echo "\n=== Cleanup ===\n";
cleanup();
echo "INFO: cleanup complete\n";
echo "\n=== All PKI-semantics live checks PASSED ===\n";
exit(0);
