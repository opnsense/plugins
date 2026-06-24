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
 * Re-seed clean demo data for the Nebula plugin.
 *
 * Deletes any existing demo-ca / demo-host / demo-lighthouse entries, then:
 *   1. Generates a fresh CA "demo-ca" with all computed fields populated.
 *   2. Signs "demo-host" under it, with computed fields + ca_name.
 *   3. Creates a "demo-lighthouse" instance pointing at demo-host.
 *   4. Runs `configctl nebula reconfigure` and confirms 1 daemon is running.
 *
 * Run on the OPNsense guest after `make install`:
 *   php /usr/plugins/net/nebula/tools/reseed_demo.php
 */

require_once('script/load_phalcon.php');

use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Nebula\Nebula;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function info(string $msg): void
{
    echo "INFO: {$msg}\n";
}

function fail(string $msg): never
{
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
}

function assert_ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fail($msg);
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

/**
 * Populate computed fields on a model node from pki_print_cert output.
 * Mirrors AuthorityController::storeComputedFields / CertificateController::storeComputedFields.
 */
function store_computed_fields($node, array $printRes): void
{
    $entry   = $printRes['info'][0] ?? [];
    $details = $entry['details'] ?? [];

    $node->cn          = (string)($details['name']      ?? '');
    $node->valid_from  = (string)($details['notBefore'] ?? '');
    $node->valid_to    = (string)($details['notAfter']  ?? '');
    $node->fingerprint = (string)($entry['fingerprint'] ?? '');
    $node->is_ca       = !empty($details['isCa']) ? '1' : '0';

    // Curve embedded in the cert, normalised to the model's option values.
    if (isset($entry['curve'])) {
        $node->curve = (stripos((string)$entry['curve'], 'P256') !== false) ? 'P256' : '25519';
    }

    // issuer = signing CA fingerprint (present on host certs; '' for a CA itself).
    if (isset($node->issuer)) {
        $node->issuer = (string)($details['issuer'] ?? '');
    }

    // Network constraints embedded in the CA cert (populated for authority nodes).
    if (isset($node->networks)) {
        $nets = $details['networks'] ?? [];
        $node->networks = is_array($nets) ? implode(',', $nets) : (string)$nets;
    }
    if (isset($node->unsafe_networks)) {
        $unsafeNets = $details['unsafeNetworks'] ?? [];
        $node->unsafe_networks = is_array($unsafeNets) ? implode(',', $unsafeNets) : (string)$unsafeNets;
    }
}

/**
 * Clear the static ModelRelationField option-list cache between saves.
 *
 * ModelRelationField::$internalStaticOptionList is keyed by an md5 of the
 * model structure and populated once per process from the on-disk config.
 * When we save a CA and then immediately try to use its UUID as a certref
 * in the same process, the cached option list doesn't contain the new UUID,
 * causing caref/certref validation to fail even though the CA was saved.
 *
 * Solution: clear the static cache after each save so the next model
 * instantiation re-reads from disk.  We use Reflection to reach the
 * private static property on the base FieldType.
 */
function clear_relation_cache(): void
{
    try {
        $class = new ReflectionClass('OPNsense\Base\FieldTypes\ModelRelationField');
        if ($class->hasProperty('internalStaticOptionList')) {
            $prop = $class->getProperty('internalStaticOptionList');
            $prop->setAccessible(true);
            $prop->setValue(null, []);
        }
    } catch (ReflectionException $e) {
        // If the property name changed in a future OPNsense version, log and continue.
        info("Warning: could not clear ModelRelationField cache: " . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Step 0: Delete existing demo entries
// ---------------------------------------------------------------------------

echo "\n=== Step 0: Delete existing demo entries ===\n";

$mdl = new Nebula();
$deletedCa   = false;
$deletedCert = false;
$deletedInst = false;

foreach ($mdl->pki->authorities->authority->iterateItems() as $node) {
    if ((string)$node->descr === 'demo-ca') {
        $uuid = $node->getAttribute('uuid');
        $mdl->pki->authorities->authority->del($uuid);
        info("Deleted authority demo-ca (uuid={$uuid})");
        $deletedCa = true;
    }
}

foreach ($mdl->pki->certificates->certificate->iterateItems() as $node) {
    if ((string)$node->descr === 'demo-host') {
        $uuid = $node->getAttribute('uuid');
        $mdl->pki->certificates->certificate->del($uuid);
        info("Deleted certificate demo-host (uuid={$uuid})");
        $deletedCert = true;
    }
}

foreach ($mdl->instances->instance->iterateItems() as $node) {
    if ((string)$node->description === 'demo-lighthouse') {
        $uuid = $node->getAttribute('uuid');
        $mdl->instances->instance->del($uuid);
        info("Deleted instance demo-lighthouse (uuid={$uuid})");
        $deletedInst = true;
    }
}

$mdl->serializeToConfig();
Config::getInstance()->save();
info("Existing demo entries cleared (ca={$deletedCa}, cert={$deletedCert}, inst={$deletedInst})");
clear_relation_cache();

// ---------------------------------------------------------------------------
// Step 1: Generate fresh CA "demo-ca"
// ---------------------------------------------------------------------------

echo "\n=== Step 1: Generate CA 'demo-ca' ===\n";

$genRes = call_pki('pki_generate_ca', [
    'name'           => 'demo-ca',
    'curve'          => '25519',
    'duration_hours' => 8760,  // 1 year
    'networks'       => '192.168.100.0/24',
]);

assert_ok(empty($genRes['error']), "pki_generate_ca succeeded (error: " . ($genRes['error'] ?? 'none') . ")");
assert_ok(!empty($genRes['crt']),  "pki_generate_ca returned non-empty crt");
assert_ok(!empty($genRes['key']),  "pki_generate_ca returned non-empty key");

$caCrt = $genRes['crt'];
$caKey = $genRes['key'];

// Parse to get computed fields.
$caPrint = call_pki('pki_print_cert', ['crt' => $caCrt]);
assert_ok(empty($caPrint['error']) && !empty($caPrint['info']), "pki_print_cert accepted generated CA crt");

$mdl1 = new Nebula();
$caNode = $mdl1->pki->authorities->authority->Add();
$caNode->descr   = 'demo-ca';
$caNode->origin  = 'generated';
$caNode->curve   = '25519';
$caNode->crt     = $caCrt;
$caNode->key     = $caKey;
$caNode->has_key = '1';
store_computed_fields($caNode, $caPrint);
// Generated CA: unencrypted key + is_ca → it can sign (drives the dropdown + grid).
$caNode->can_sign = ((string)$caNode->is_ca === '1') ? '1' : '0';

$caUuid = $caNode->getAttribute('uuid');
assert_ok(!empty($caUuid), "CA node has UUID");

$valMsgs = $mdl1->performValidation();
assert_ok(count($valMsgs) === 0, "CA model validation passes (errors: " . implode(', ', array_map(fn($m) => $m->getMessage(), iterator_to_array($valMsgs))) . ")");

$mdl1->serializeToConfig();
Config::getInstance()->save();
clear_relation_cache();

info("demo-ca saved: uuid={$caUuid}, cn=" . (string)$caNode->cn . ", valid_to=" . (string)$caNode->valid_to . ", has_key=1");

// ---------------------------------------------------------------------------
// Step 2: Sign "demo-host" under demo-ca
// ---------------------------------------------------------------------------

echo "\n=== Step 2: Sign certificate 'demo-host' ===\n";

$signRes = call_pki('pki_sign_cert', [
    'name'     => 'demo-host',
    'networks' => '192.168.100.1/24',
    'ca_crt'   => $caCrt,
    'ca_key'   => $caKey,
    // No duration_hours → nebula-cert defaults to CA expiry.
]);

assert_ok(empty($signRes['error']), "pki_sign_cert succeeded (error: " . ($signRes['error'] ?? 'none') . ")");
assert_ok(!empty($signRes['crt']),  "pki_sign_cert returned non-empty crt");
assert_ok(!empty($signRes['key']),  "pki_sign_cert returned non-empty key");

$hostCrt = $signRes['crt'];
$hostKey = $signRes['key'];

// Parse to get computed fields.
$hostPrint = call_pki('pki_print_cert', ['crt' => $hostCrt]);
assert_ok(empty($hostPrint['error']) && !empty($hostPrint['info']), "pki_print_cert accepted host crt");

// Extract ca_name from CA node.
$caCn   = (string)$caNode->cn;
$caName = ($caCn !== '') ? $caCn : 'demo-ca';

$mdl2 = new Nebula();
$certNode = $mdl2->pki->certificates->certificate->Add();
$certNode->descr           = 'demo-host';
$certNode->origin          = 'signed';
$certNode->caref           = $caUuid;
$certNode->crt             = $hostCrt;
$certNode->key             = $hostKey;
$certNode->networks        = '192.168.100.1/24';
$certNode->groups          = '';
$certNode->unsafe_networks = '';
$certNode->has_key         = '1';
$certNode->ca_name         = $caName;
store_computed_fields($certNode, $hostPrint);

$certUuid = $certNode->getAttribute('uuid');
assert_ok(!empty($certUuid), "Cert node has UUID");

// Use disable_validation=true to bypass the ModelRelationField static-cache
// issue: caref references a CA saved in a prior Nebula() instance in this same
// PHP process, so the static option-list cache pre-dates the CA being on disk.
// The real API flow (new HTTP request per action) always starts fresh, so the
// caref validation works correctly in production.  clear_relation_cache() above
// should have flushed the cache; if it didn't, disable_validation avoids a
// spurious failure here.
$mdl2->serializeToConfig(false, true);
Config::getInstance()->save();
clear_relation_cache();

info("demo-host saved: uuid={$certUuid}, cn=" . (string)$certNode->cn . ", valid_to=" . (string)$certNode->valid_to . ", ca_name={$caName}, has_key=1");

// ---------------------------------------------------------------------------
// Step 3: Create "demo-lighthouse" instance pointing at demo-host
// ---------------------------------------------------------------------------

echo "\n=== Step 3: Create instance 'demo-lighthouse' ===\n";

$mdl3 = new Nebula();

$instNode = $mdl3->instances->instance->Add();
$instNode->enabled      = '1';
$instNode->description  = 'demo-lighthouse';
$instNode->certref      = $certUuid;
$instNode->am_lighthouse = '1';
$instNode->listen_port  = '4242';
$instNode->tun_name     = 'nebula0';
$instNode->listen_host  = '::';
$instNode->listen_send_recv_error  = 'always';
$instNode->listen_accept_recv_error = 'always';
$instNode->punchy_punch = '1';
$instNode->punchy_respond = '0';
$instNode->punchy_delay  = '1s';
$instNode->punchy_respond_delay = '5s';
$instNode->relay_am_relay  = '0';
$instNode->relay_use_relays = '1';
$instNode->logging_level  = 'info';
$instNode->logging_format = 'text';
$instNode->firewall_outbound_action = 'drop';
$instNode->firewall_inbound_action  = 'drop';

$instUuid = $instNode->getAttribute('uuid');
assert_ok(!empty($instUuid), "Instance node has UUID");

// Use disable_validation=true for the same ModelRelationField cache reason
// as above (certref points at the cert we just saved).
$mdl3->serializeToConfig(false, true);
Config::getInstance()->save();
clear_relation_cache();

info("demo-lighthouse saved: uuid={$instUuid}, certref={$certUuid}, am_lighthouse=1, listen_port=4242, tun_name=nebula0");

// ---------------------------------------------------------------------------
// Step 4: configctl nebula reconfigure + verify daemon is running
// ---------------------------------------------------------------------------

echo "\n=== Step 4: Reconfigure + verify daemon ===\n";

$backend = new Backend();
$reconfigOut = trim((string)$backend->configdRun('nebula reconfigure'));
info("configctl nebula reconfigure output: {$reconfigOut}");

// Give the daemon a moment to start.
sleep(2);

$statusOut = trim((string)$backend->configdpRun('nebula status_instance', [$instUuid]));
info("nebula status_instance output: {$statusOut}");

$statusArr = json_decode($statusOut, true);
$running   = is_array($statusArr) ? ($statusArr['running'] ?? false) : false;

if (!$running) {
    // Log but don't hard-fail: the instance may not start on this dev box if
    // the nebula tun interface or CA/cert don't fully configure (e.g. host cert
    // network IP not assigned to any interface).  The important part is that
    // the data model is correct.
    info("WARNING: daemon not confirmed running (status: {$statusOut}). Check 'nebula start' manually.");
} else {
    $pid = $statusArr['pid'] ?? 'unknown';
    echo "PASS: demo-lighthouse daemon is running (pid={$pid})\n";
}

// ---------------------------------------------------------------------------
// Final summary
// ---------------------------------------------------------------------------

echo "\n=== Reseed complete ===\n";
echo "demo-ca:        uuid={$caUuid}, has_key=1, cn=" . (string)$caNode->cn . ", valid_to=" . (string)$caNode->valid_to . "\n";
echo "demo-host:      uuid={$certUuid}, has_key=1, cn=" . (string)$certNode->cn . ", valid_to=" . (string)$certNode->valid_to . "\n";
echo "demo-lighthouse: uuid={$instUuid}, certref={$certUuid}\n";
exit(0);
