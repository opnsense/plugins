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

namespace OPNsense\Nebula\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * Authority API — CA CRUD, generate, and import for the Nebula PKI.
 *
 * Endpoints:
 *   GET  /api/nebula/authority/searchItem
 *   GET  /api/nebula/authority/getItem/<uuid>
 *   POST /api/nebula/authority/setItem/<uuid>
 *   POST /api/nebula/authority/delItem/<uuid>
 *   POST /api/nebula/authority/generate    — invoke nebula-cert ca via configd
 *   POST /api/nebula/authority/import      — store a pre-existing CA cert + key
 *
 * @package OPNsense\Nebula\Api
 */
class AuthorityController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'nebula';
    protected static $internalModelClass = 'OPNsense\Nebula\Nebula';

    // -------------------------------------------------------------------------
    // Standard CRUD (no plain addItem — use generate or import)
    // -------------------------------------------------------------------------

    public function searchItemAction()
    {
        // The grid needs descr, cn, curve, valid_to, networks, unsafe_networks and
        // can_sign.  We also expose has_key (drives the Download-key button), plus
        // fingerprint + can_sign so the Sign-dialog dropdown in certificates.volt can
        // filter to signable CAs and disambiguate same-named ones by short fingerprint.
        // searchBase()'s field list governs *searching*, not which columns are
        // serialised, so we post-filter every row to strip the PEM material — in
        // particular the private 'key' must NEVER reach the grid JSON.
        // key_encrypted is also returned so the Sign dialog can decide whether to
        // require a passphrase for the selected CA.
        $result = $this->searchBase(
            'pki.authorities.authority',
            ['descr', 'cn', 'curve', 'valid_to', 'has_key', 'can_sign', 'key_encrypted', 'fingerprint', 'networks', 'unsafe_networks']
        );
        if (!empty($result['rows'])) {
            foreach ($result['rows'] as &$row) {
                unset($row['key'], $row['crt']);
            }
        }
        return $result;
    }

    /**
     * Parse a nebula-cert print result and populate the computed read-only
     * model fields (cn, valid_from, valid_to, fingerprint, is_ca) on $node.
     *
     * $printRes is the decoded ['info' => [...]] returned by pki_print_cert,
     * where info[0] = {curve, details:{name,isCa,networks,groups,notBefore,
     * notAfter,...}, fingerprint, ...}.
     */
    private function storeComputedFields($node, array $printRes): void
    {
        $entry   = $printRes['info'][0] ?? [];
        $details = $entry['details'] ?? [];

        $node->cn          = (string)($details['name'] ?? '');
        $node->valid_from  = (string)($details['notBefore'] ?? '');
        $node->valid_to    = (string)($details['notAfter'] ?? '');
        $node->fingerprint = (string)($entry['fingerprint'] ?? '');
        $node->is_ca       = !empty($details['isCa']) ? '1' : '0';

        // Curve as embedded in the cert (info[0].curve, e.g. "CURVE25519" / "P256").
        // Normalise to the model's option values: 25519 / P256.
        if (isset($entry['curve'])) {
            $node->curve = (stripos((string)$entry['curve'], 'P256') !== false) ? 'P256' : '25519';
        }

        // Network constraints embedded in the CA cert (empty = unrestricted).
        $nets = $details['networks'] ?? [];
        $node->networks = is_array($nets) ? implode(',', $nets) : (string)$nets;

        $unsafeNets = $details['unsafeNetworks'] ?? [];
        $node->unsafe_networks = is_array($unsafeNets) ? implode(',', $unsafeNets) : (string)$unsafeNets;
    }

    /**
     * Whether a stored PEM private key is encrypted (passphrase-protected).
     *
     * Nebula encrypted keys carry an "ENCRYPTED" token in the PEM header, e.g.
     * "-----BEGIN NEBULA ED25519 ENCRYPTED PRIVATE KEY-----".  As of INFRA-163 an
     * encrypted CA IS signable: the passphrase is supplied per signing operation
     * and passed to nebula-cert via NEBULA_CA_PASSPHRASE (never stored).  This
     * flag drives the Sign dialog's conditional passphrase prompt (key_encrypted),
     * not whether the CA can sign.
     */
    private function keyIsEncrypted(string $key): bool
    {
        return stripos($key, 'ENCRYPTED') !== false;
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase('authority', 'pki.authorities.authority', $uuid);
    }

    public function setItemAction($uuid = null)
    {
        return $this->setBase('authority', 'pki.authorities.authority', $uuid);
    }

    public function delItemAction($uuid = null)
    {
        // Referential integrity: refuse to delete a CA still signing a cert
        // (caref) or trusted by an instance (trusted_cas). The check lives on the
        // model so delete and purge share it.
        $ref = $this->getModel()->caReferencedBy((string)$uuid);
        if ($ref !== null) {
            return [
                'result'  => 'failed',
                'message' => sprintf('CA is in use by %s and cannot be deleted.', $ref),
            ];
        }
        return $this->delBase('pki.authorities.authority', $uuid);
    }

    // -------------------------------------------------------------------------
    // POST /api/nebula/authority/purge_expired
    // -------------------------------------------------------------------------

    /**
     * Delete every expired CA that is not still referenced; an expired-but-
     * referenced CA is kept and named in the result so the admin can resolve the
     * reference first. The selection/deletion lives on the model
     * (Nebula::purgeExpiredAuthorities); this action just persists and reports.
     *
     * Returns {"result":"saved","removed":N,"skipped":M,"skippedNames":[...]}.
     */
    public function purgeExpiredAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'error' => 'POST required'];
        }

        $mdl = $this->getModel();
        $res = $mdl->purgeExpiredAuthorities();

        if ($res['removed'] > 0) {
            $valMsgs = $mdl->performValidation();
            if (count($valMsgs) > 0) {
                $validations = [];
                foreach ($valMsgs as $msg) {
                    $parts = explode('.', $msg->getField());
                    $validations[end($parts)] = $msg->getMessage();
                }
                return ['result' => 'failed', 'validations' => $validations];
            }
            $mdl->serializeToConfig();
            Config::getInstance()->save();
        }

        return [
            'result'       => 'saved',
            'removed'      => $res['removed'],
            'skipped'      => count($res['skippedNames']),
            'skippedNames' => $res['skippedNames'],
        ];
    }

    // -------------------------------------------------------------------------
    // POST /api/nebula/authority/generate
    // -------------------------------------------------------------------------

    /**
     * Generate a new Nebula CA via configd → pki.php generate-ca → nebula-cert.
     *
     * Request body (JSON or form-encoded):
     *   name           string  required  Nebula CA name embedded in the certificate
     *   descr          string  optional  Human-readable OPNsense label (defaults to name)
     *   curve          string  optional  25519 (default) or P256
     *   duration_days  int     optional  Validity in days (default 365)
     *   groups         string  optional  Comma-separated group list
     *   networks       string  optional  Comma-separated CIDR list
     *   unsafe_networks string optional  Comma-separated unsafe CIDR list
     *   passphrase     string  optional  When non-empty the CA private key is
     *                                    encrypted with this passphrase (passed to
     *                                    nebula-cert via NEBULA_CA_PASSPHRASE, never
     *                                    stored).  Empty → unencrypted key as before.
     *
     * Returns:
     *   {"result":"saved","uuid":"<uuid>"}  on success
     *   {"result":"failed","error":"..."}   on configd / nebula-cert failure
     *   {"result":"failed","validations":{...}}  on model validation failure
     */
    public function generateAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'error' => 'POST required'];
        }

        $name            = trim($this->request->getPost('name', 'string', ''));
        $descr           = trim($this->request->getPost('descr', 'string', ''));
        $curve           = $this->request->getPost('curve', 'string', '25519');
        $duration_days   = (int)$this->request->getPost('duration_days', 'int', 365);
        $groups          = $this->request->getPost('groups', 'string', '');
        $networks        = $this->request->getPost('networks', 'string', '');
        $unsafe_networks = $this->request->getPost('unsafe_networks', 'string', '');
        // Optional CA-key passphrase: NOT trimmed (a passphrase may legitimately
        // contain leading/trailing whitespace) and NEVER stored — only forwarded
        // to pki.php, which hands it to nebula-cert via NEBULA_CA_PASSPHRASE.
        $passphrase      = $this->request->getPost('passphrase', 'string', '');

        if ($name === '') {
            return ['result' => 'failed', 'validations' => ['name' => 'name is required']];
        }

        // descr defaults to name if the user left it blank.
        if ($descr === '') {
            $descr = $name;
        }

        // duration_hours is passed as an integer; pki.php formats the "Nh" string.
        $pkiParams = [
            'name'           => $name,
            'curve'          => $curve,
            'duration_hours' => $duration_days * 24,
        ];
        if ($groups !== '') {
            $pkiParams['groups'] = $groups;
        }
        if ($networks !== '') {
            $pkiParams['networks'] = $networks;
        }
        if ($unsafe_networks !== '') {
            $pkiParams['unsafe_networks'] = $unsafe_networks;
        }
        // Non-empty passphrase → pki.php adds -encrypt and runs nebula-cert with
        // NEBULA_CA_PASSPHRASE set.  The passphrase is forwarded but never stored.
        if ($passphrase !== '') {
            $pkiParams['passphrase'] = $passphrase;
        }

        $b64 = base64_encode(json_encode($pkiParams));
        $out = (new Backend())->configdpRun('nebula pki_generate_ca', [$b64]);
        $res = json_decode($out, true);

        if (!is_array($res) || !empty($res['error']) || empty($res['crt']) || empty($res['key'])) {
            $err = (is_array($res) && !empty($res['error'])) ? $res['error'] : 'configd returned no result';
            return ['result' => 'failed', 'error' => $err];
        }

        $mdl  = $this->getModel();
        $node = $mdl->pki->authorities->authority->Add();
        $node->descr  = $descr;
        $node->origin = 'generated';
        $node->curve  = $curve;
        $node->crt    = $res['crt'];
        $node->key    = $res['key'];
        $node->has_key = '1';
        // key_encrypted reflects whether the stored key PEM is passphrase-protected.
        // It is true exactly when the user supplied a passphrase; we also confirm it
        // against the returned PEM header so the two never drift.
        $node->key_encrypted = ($passphrase !== '' || $this->keyIsEncrypted((string)$res['key'])) ? '1' : '0';

        // Parse the freshly minted CA cert once and store its computed fields.
        $printB64 = base64_encode(json_encode(['crt' => $res['crt']]));
        $printOut = (new Backend())->configdpRun('nebula pki_print_cert', [$printB64]);
        $printRes = json_decode($printOut, true);
        if (is_array($printRes) && empty($printRes['error']) && !empty($printRes['info'])) {
            $this->storeComputedFields($node, $printRes);
        }

        // A freshly generated CA has its private key stored (encrypted or not), so it
        // is a valid signer as long as the cert is a CA (always true for
        // nebula-cert ca).  Encrypted CAs sign with a per-operation passphrase.
        $node->can_sign = ((string)$node->is_ca === '1') ? '1' : '0';

        $uuid = $node->getAttribute('uuid');

        $valMsgs = $mdl->performValidation();
        if (count($valMsgs) > 0) {
            $validations = [];
            foreach ($valMsgs as $msg) {
                $field = $msg->getField();
                // Strip the model prefix (e.g. "nebula.pki.authorities.authority.<uuid>.descr")
                // down to just the leaf field for a friendly response.
                $parts = explode('.', $field);
                $leaf  = end($parts);
                $validations[$leaf] = $msg->getMessage();
            }
            return ['result' => 'failed', 'validations' => $validations];
        }

        $mdl->serializeToConfig();
        Config::getInstance()->save();

        return ['result' => 'saved', 'uuid' => $uuid];
    }

    // -------------------------------------------------------------------------
    // POST /api/nebula/authority/generate_file/<uuid>/<type>
    // -------------------------------------------------------------------------

    /**
     * Return the stored PEM for a CA as a downloadable payload (Trust idiom).
     *
     * Mirrors Trust\Api\CaController::generateFileAction — the response is JSON
     * so the caller can use download_content() in JS.
     *
     * @param string $uuid CA authority uuid
     * @param string $type 'crt' (default) or 'key'
     * @return array {'status':'ok','payload':'<PEM>','descr':'<name>'} or error
     */
    public function generateFileAction($uuid = null, $type = 'crt')
    {
        $result = ['status' => 'failed'];
        if ($this->request->isPost() && !empty($uuid)) {
            $node = null;
            foreach ($this->getModel()->pki->authorities->authority->iterateItems() as $item) {
                if ($item->getAttribute('uuid') === $uuid) {
                    $node = $item;
                    break;
                }
            }
            if ($node === null) {
                $result['error'] = 'authority not found';
                return $result;
            }
            $result['descr'] = (string)$node->descr;
            if ($type === 'crt') {
                $crt = (string)$node->crt;
                if ($crt === '') {
                    $result['error'] = 'no certificate stored';
                } else {
                    $result['status']  = 'ok';
                    $result['payload'] = $crt;
                }
            } elseif ($type === 'key') {
                $key = (string)$node->key;
                if ($key === '') {
                    $result['error'] = 'no private key stored for this authority';
                } else {
                    $result['status']  = 'ok';
                    $result['payload'] = $key;
                }
            } else {
                $result['error'] = 'unsupported type';
            }
        }
        return $result;
    }

    // -------------------------------------------------------------------------
    // GET /api/nebula/authority/info/<uuid>
    // -------------------------------------------------------------------------

    /**
     * Return parsed certificate details for the GUI inspect view.
     *
     * Mirrors CertificateController::infoAction — calls pki_print_cert on the
     * stored crt and returns the parsed info array.
     *
     * @param string $uuid CA authority uuid
     * @return array {'info':[...]} or {'result':'failed','error':'...'}
     */
    public function infoAction($uuid = null)
    {
        if (empty($uuid)) {
            return ['result' => 'failed', 'error' => 'uuid is required'];
        }

        $node = null;
        foreach ($this->getModel()->pki->authorities->authority->iterateItems() as $item) {
            if ($item->getAttribute('uuid') === $uuid) {
                $node = $item;
                break;
            }
        }

        if ($node === null) {
            return ['result' => 'failed', 'error' => 'authority not found'];
        }

        $crt = (string)$node->crt;
        if ($crt === '') {
            return ['result' => 'failed', 'error' => 'authority has no crt data'];
        }

        $b64      = base64_encode(json_encode(['crt' => $crt]));
        $out      = (new Backend())->configdpRun('nebula pki_print_cert', [$b64]);
        $printRes = json_decode($out, true);

        if (!is_array($printRes) || !empty($printRes['error']) || empty($printRes['info'])) {
            $err = (is_array($printRes) && !empty($printRes['error'])) ? $printRes['error'] : 'pki_print_cert returned no info';
            return ['result' => 'failed', 'error' => $err];
        }

        return ['info' => $printRes['info']];
    }

    // -------------------------------------------------------------------------
    // POST /api/nebula/authority/import
    // -------------------------------------------------------------------------

    /**
     * Import an existing Nebula CA certificate (and optionally its private key).
     *
     * Request body (JSON or form-encoded):
     *   descr  string  required  Human-readable name
     *   crt    string  required  PEM-encoded Nebula CA certificate
     *   key    string  optional  PEM-encoded private key (may be omitted for
     *                            cert-only imports, e.g. trust anchors)
     *
     * Returns:
     *   {"result":"saved","uuid":"<uuid>"}                     on success
     *   {"result":"failed","validations":{"crt":"..."}}        if crt is invalid
     *   {"result":"failed","validations":{"descr":"..."}}      if descr is missing
     */
    /**
     * Validate a PEM-encoded Nebula private key string.
     *
     * Accepts any "-----BEGIN NEBULA ... PRIVATE KEY-----" header (ED25519,
     * X25519, P256 variants), INCLUDING encrypted keys
     * ("-----BEGIN NEBULA ... ENCRYPTED PRIVATE KEY-----").  Encrypted keys are
     * stored as-is; the CA simply cannot sign until the passphrase plumbing lands
     * (INFRA-163), reflected by can_sign='0'.  Only a string that is not a Nebula
     * private key PEM at all is rejected.
     *
     * Returns null on success, or a user-facing error string on failure.
     */
    private function validateKeyPem(string $key): ?string
    {
        // Must contain a NEBULA * PRIVATE KEY PEM header (encrypted variants too).
        if (!preg_match('/-----BEGIN NEBULA [A-Z0-9 ]*PRIVATE KEY-----/', $key)) {
            return 'not a valid Nebula private key';
        }
        return null;
    }

    public function importAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'error' => 'POST required'];
        }

        $descr = trim($this->request->getPost('descr', 'string', ''));
        $crt   = $this->request->getPost('crt', 'string', '');
        $key   = $this->request->getPost('key', 'string', '');

        if ($descr === '') {
            return ['result' => 'failed', 'validations' => ['descr' => 'descr is required']];
        }
        if ($crt === '') {
            return ['result' => 'failed', 'validations' => ['crt' => 'crt is required']];
        }

        // Validate crt by asking pki.php print-cert (exit ≠ 0 → invalid).
        $printParams = ['crt' => $crt];
        $b64         = base64_encode(json_encode($printParams));
        $out         = (new Backend())->configdpRun('nebula pki_print_cert', [$b64]);
        $printRes    = json_decode($out, true);

        if (!is_array($printRes) || !empty($printRes['error']) || empty($printRes['info'])) {
            return [
                'result'      => 'failed',
                'validations' => ['crt' => 'not a valid Nebula certificate'],
            ];
        }

        // A CA must actually be a CA certificate (isCa=true).  Reject host certs.
        $details = $printRes['info'][0]['details'] ?? [];
        if (empty($details['isCa'])) {
            return [
                'result'      => 'failed',
                'validations' => ['crt' => 'this certificate is not a CA (isCa=false)'],
            ];
        }

        // Validate the private key if one was supplied (encrypted keys are accepted).
        if ($key !== '') {
            $keyErr = $this->validateKeyPem($key);
            if ($keyErr !== null) {
                return ['result' => 'failed', 'validations' => ['key' => $keyErr]];
            }
        }

        $mdl  = $this->getModel();

        // Reject a duplicate import (same CA cert fingerprint already in the pool).
        // This also covers "I imported the cert alone, now I want to add its key":
        // the user must delete the existing cert-only entry first and re-import the
        // pair, rather than us silently creating a second row or mutating an
        // existing one.
        $fingerprint = (string)($printRes['info'][0]['fingerprint'] ?? '');
        if ($fingerprint !== '') {
            foreach ($mdl->pki->authorities->authority->iterateItems() as $existing) {
                if ((string)$existing->fingerprint === $fingerprint) {
                    return [
                        'result'      => 'failed',
                        'validations' => ['crt' => 'this CA is already imported (delete the existing entry first to replace it)'],
                    ];
                }
            }
        }

        $node = $mdl->pki->authorities->authority->Add();
        $node->descr  = $descr;
        $node->origin = 'imported';
        $node->crt    = $crt;
        if ($key !== '') {
            $node->key           = $key;
            $node->has_key       = '1';
            // Detect encryption from the imported key PEM header so the Sign dialog
            // knows whether to prompt for a passphrase.
            $node->key_encrypted = $this->keyIsEncrypted($key) ? '1' : '0';
        } else {
            $node->has_key       = '0';
            $node->key_encrypted = '0';
        }

        // Store computed fields (cn, valid_*, fingerprint, is_ca, curve, networks)
        // from the print output we already obtained above.
        $this->storeComputedFields($node, $printRes);

        // can_sign = is_ca AND a private key is present.  Encrypted keys ARE
        // signable now (INFRA-163): the passphrase is supplied per signing
        // operation via NEBULA_CA_PASSPHRASE and never stored, so encryption no
        // longer excludes a CA from signing.
        $canSign = ((string)$node->is_ca === '1') && $key !== '';
        $node->can_sign = $canSign ? '1' : '0';

        $uuid = $node->getAttribute('uuid');

        $valMsgs = $mdl->performValidation();
        if (count($valMsgs) > 0) {
            $validations = [];
            foreach ($valMsgs as $msg) {
                $parts = explode('.', $msg->getField());
                $leaf  = end($parts);
                $validations[$leaf] = $msg->getMessage();
            }
            return ['result' => 'failed', 'validations' => $validations];
        }

        $mdl->serializeToConfig();
        Config::getInstance()->save();

        return ['result' => 'saved', 'uuid' => $uuid];
    }
}
