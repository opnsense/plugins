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
 * Certificate API — host certificate CRUD, sign, import, and info for the Nebula PKI.
 *
 * Endpoints:
 *   GET  /api/nebula/certificate/searchItem
 *   GET  /api/nebula/certificate/getItem/<uuid>
 *   POST /api/nebula/certificate/setItem/<uuid>
 *   POST /api/nebula/certificate/delItem/<uuid>
 *   POST /api/nebula/certificate/sign      — nebula-cert sign via configd
 *   POST /api/nebula/certificate/import    — store a pre-signed cert + key
 *   GET  /api/nebula/certificate/info/<uuid> — pki_print_cert details for GUI
 *
 * @package OPNsense\Nebula\Api
 */
class CertificateController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'nebula';
    protected static $internalModelClass = 'OPNsense\Nebula\Nebula';

    // -------------------------------------------------------------------------
    // Standard CRUD (no plain addItem — use sign or import)
    // -------------------------------------------------------------------------

    public function searchItemAction()
    {
        // The grid needs descr, cn, CA (resolved), curve, networks, unsafe_networks
        // and valid_to.  searchBase()'s field list governs *searching*, not which
        // columns are serialised, so we post-filter every row to strip the PEM
        // material — in particular the private 'key' must NEVER reach the grid JSON.
        $result = $this->searchBase(
            'pki.certificates.certificate',
            ['descr', 'cn', 'issuer', 'curve', 'networks', 'unsafe_networks', 'valid_to', 'has_key', 'fingerprint']
        );

        if (!empty($result['rows'])) {
            // Build a fingerprint → display-name map of the current authorities so we
            // can resolve each cert's CA dynamically from its stored issuer fingerprint.
            // A cert whose issuer is not (yet) in the pool shows "unknown: <fingerprint>";
            // it auto-resolves once that CA is imported.  We never rely on a stored
            // ca_name — the issuer fingerprint is the source of truth.
            $caByFingerprint = [];
            foreach ($this->getModel()->pki->authorities->authority->iterateItems() as $ca) {
                $fp = (string)$ca->fingerprint;
                if ($fp !== '') {
                    $caByFingerprint[$fp] = (string)$ca->cn !== '' ? (string)$ca->cn : (string)$ca->descr;
                }
            }

            foreach ($result['rows'] as &$row) {
                // Render the issuing CA as "name: 0123abcd" (CA name + first 8 hex
                // of its fingerprint, which is the cert's stored issuer) so
                // like-named CAs are distinguishable; "unknown: 0123abcd" when the
                // issuer CA is not (yet) in the pool.
                $issuer = (string)($row['issuer'] ?? '');
                if ($issuer !== '' && isset($caByFingerprint[$issuer])) {
                    $row['ca_name'] = $caByFingerprint[$issuer] . ': ' . substr($issuer, 0, 8);
                } elseif ($issuer !== '') {
                    $row['ca_name'] = gettext('unknown') . ': ' . substr($issuer, 0, 8);
                } else {
                    $row['ca_name'] = '';
                }
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
        // issuer = fingerprint of the signing CA; the CA's display name is resolved
        // dynamically at render in searchItemAction (never stored as ca_name).
        $node->issuer      = (string)($details['issuer'] ?? '');

        // Curve as embedded in the cert (info[0].curve, e.g. "CURVE25519" / "P256").
        if (isset($entry['curve'])) {
            $node->curve = (stripos((string)$entry['curve'], 'P256') !== false) ? 'P256' : '25519';
        }
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase('certificate', 'pki.certificates.certificate', $uuid);
    }

    public function setItemAction($uuid = null)
    {
        return $this->setBase('certificate', 'pki.certificates.certificate', $uuid);
    }

    public function delItemAction($uuid = null)
    {
        // Referential integrity: refuse to delete a cert still referenced by an
        // instance (certref). The check lives on the model so delete and purge
        // share it.
        $ref = $this->getModel()->certReferencedBy((string)$uuid);
        if ($ref !== null) {
            return [
                'result'  => 'failed',
                'message' => sprintf('Certificate is in use by %s and cannot be deleted.', $ref),
            ];
        }
        return $this->delBase('pki.certificates.certificate', $uuid);
    }

    // -------------------------------------------------------------------------
    // POST /api/nebula/certificate/purge_expired
    // -------------------------------------------------------------------------

    /**
     * Delete every expired certificate that is not still referenced by an
     * instance; an expired-but-referenced cert is kept and named in the result.
     * The selection/deletion lives on the model
     * (Nebula::purgeExpiredCertificates); this action just persists and reports.
     *
     * Returns {"result":"saved","removed":N,"skipped":M,"skippedNames":[...]}.
     */
    public function purgeExpiredAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'error' => 'POST required'];
        }

        $mdl = $this->getModel();
        $res = $mdl->purgeExpiredCertificates();

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
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve a CA authority node from the model by UUID.
     * Returns the authority node, or null if not found.
     */
    private function findCaByUuid($mdl, string $caref)
    {
        foreach ($mdl->pki->authorities->authority->iterateItems() as $node) {
            if ($node->getAttribute('uuid') === $caref) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Call a nebula pki configd action with base64-encoded JSON params.
     * Returns the decoded result array, or ['error' => '...'] on failure.
     */
    private function callPki(string $action, array $params): array
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
     * Persist a certificate node: run validation, serialize, save.
     * Returns ['result'=>'saved','uuid'=>...] or ['result'=>'failed','validations'=>[...]].
     */
    private function saveCertNode($mdl, $node): array
    {
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

    // -------------------------------------------------------------------------
    // POST /api/nebula/certificate/generate_file/<uuid>/<type>
    // -------------------------------------------------------------------------

    /**
     * Return the stored PEM for a host certificate as a downloadable payload.
     *
     * Mirrors Trust\Api\CaController::generateFileAction — response is JSON so
     * the caller uses download_content() in JS (Trust idiom).
     *
     * @param string $uuid certificate uuid
     * @param string $type 'crt' (default) or 'key'
     * @return array {'status':'ok','payload':'<PEM>','descr':'<name>'} or error
     */
    public function generateFileAction($uuid = null, $type = 'crt')
    {
        $result = ['status' => 'failed'];
        if ($this->request->isPost() && !empty($uuid)) {
            $node = null;
            foreach ($this->getModel()->pki->certificates->certificate->iterateItems() as $item) {
                if ($item->getAttribute('uuid') === $uuid) {
                    $node = $item;
                    break;
                }
            }
            if ($node === null) {
                $result['error'] = 'certificate not found';
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
                    $result['error'] = 'no private key stored for this certificate';
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
    // POST /api/nebula/certificate/sign
    // -------------------------------------------------------------------------

    /**
     * Validate a Nebula PUBLIC key PEM string.
     *
     * Accepts any "-----BEGIN NEBULA ... PUBLIC KEY-----" header (ED25519,
     * X25519, P256 variants).  Returns null on success, or a user-facing
     * error string on failure.
     */
    private function validatePublicKeyPem(string $pub): ?string
    {
        if (!preg_match('/-----BEGIN NEBULA [A-Z0-9 ]*PUBLIC KEY-----/', $pub)) {
            return 'not a valid Nebula public key';
        }
        return null;
    }

    /**
     * Sign a new Nebula host certificate under an existing CA via configd.
     *
     * Request body (JSON or form-encoded):
     *   descr          string  required  Human-readable label stored in the model
     *   caref          string  required  UUID of the CA authority to sign with
     *   name           string  required  Nebula cert name / hostname
     *   networks       string  required  Comma-separated CIDR(s) — e.g. 10.10.0.1/24
     *   groups         string  optional  Comma-separated group list
     *   unsafe_networks string optional  Comma-separated unsafe CIDR(s)
     *   duration_days  int     optional  Validity in days (omit or 0 to expire just before the CA)
     *   public_key     string  optional  PEM public key from `nebula-cert keygen` (CSR flow).
     *                                    When provided the private key is never sent to or stored
     *                                    on the CA; the returned cert has has_key='0'.
     *   passphrase     string  optional  Required only when the chosen CA's key is encrypted.
     *                                    Forwarded to nebula-cert via NEBULA_CA_PASSPHRASE to
     *                                    decrypt the CA key for this signing operation; never stored.
     *
     * Returns:
     *   {"result":"saved","uuid":"<uuid>"}
     *   {"result":"failed","error":"..."}
     *   {"result":"failed","validations":{...}}
     */
    public function signAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'error' => 'POST required'];
        }

        $descr           = trim($this->request->getPost('descr', 'string', ''));
        $caref           = trim($this->request->getPost('caref', 'string', ''));
        $name            = trim($this->request->getPost('name', 'string', ''));
        $networks        = trim($this->request->getPost('networks', 'string', ''));
        $groups          = $this->request->getPost('groups', 'string', '');
        $unsafe_networks = $this->request->getPost('unsafe_networks', 'string', '');
        $duration_days   = (int)$this->request->getPost('duration_days', 'int', 0);
        $public_key      = $this->request->getPost('public_key', 'string', '');
        // CA-key passphrase: NOT trimmed (whitespace may be significant) and NEVER
        // stored — only forwarded to pki.php for an encrypted CA key.
        $passphrase      = $this->request->getPost('passphrase', 'string', '');

        // Input guards.
        if ($name === '') {
            return ['result' => 'failed', 'validations' => ['name' => 'name is required']];
        }
        // Description defaults to the certificate name when left blank (matches CA generate).
        if ($descr === '') {
            $descr = $name;
        }
        if ($caref === '') {
            return ['result' => 'failed', 'validations' => ['caref' => 'caref is required']];
        }
        if ($networks === '') {
            return ['result' => 'failed', 'validations' => ['networks' => 'networks is required']];
        }

        // Validate public_key PEM when provided (CSR flow).
        if ($public_key !== '') {
            $pubErr = $this->validatePublicKeyPem($public_key);
            if ($pubErr !== null) {
                return ['result' => 'failed', 'validations' => ['public_key' => $pubErr]];
            }
        }

        // Resolve the CA authority from the model.
        $mdl = $this->getModel();
        $ca  = $this->findCaByUuid($mdl, $caref);
        if ($ca === null) {
            return ['result' => 'failed', 'validations' => ['caref' => 'unknown CA']];
        }

        $caCrt = (string)$ca->crt;
        $caKey = (string)$ca->key;

        if ($caCrt === '' || $caKey === '') {
            return ['result' => 'failed', 'error' => 'CA has no certificate or key stored'];
        }

        // If the CA key is encrypted, a passphrase is mandatory to decrypt it for
        // signing.  We trust the model's key_encrypted flag but also fall back to
        // inspecting the PEM header so an older un-flagged row still behaves.
        $caEncrypted = ((string)$ca->key_encrypted === '1') || (stripos($caKey, 'ENCRYPTED') !== false);
        if ($caEncrypted && $passphrase === '') {
            return [
                'result'      => 'failed',
                'validations' => ['passphrase' => 'this CA is encrypted; a passphrase is required'],
            ];
        }

        // Build sign params and call configd.
        // When duration_days is 0 / unset we omit duration_hours entirely so that
        // nebula-cert uses its built-in default: the cert expires just before the CA.
        $pkiParams = [
            'name'     => $name,
            'networks' => $networks,
            'ca_crt'   => $caCrt,
            'ca_key'   => $caKey,
        ];
        if ($duration_days > 0) {
            $pkiParams['duration_hours'] = $duration_days * 24;
        }
        if ($groups !== '') {
            $pkiParams['groups'] = $groups;
        }
        if ($unsafe_networks !== '') {
            $pkiParams['unsafe_networks'] = $unsafe_networks;
        }
        // CSR flow: pass the public key; pki.php will use -in-pub and omit -out-key.
        if ($public_key !== '') {
            $pkiParams['in_pub'] = $public_key;
        }
        // Forward the passphrase for an encrypted CA key (never stored).
        if ($passphrase !== '') {
            $pkiParams['passphrase'] = $passphrase;
        }

        $res = $this->callPki('pki_sign_cert', $pkiParams);

        // CSR mode returns key=''; generate-here mode returns a non-empty key.
        if (!empty($res['error']) || empty($res['crt'])) {
            $err = $res['error'] ?? 'configd returned no result';
            // Map nebula-cert's encrypted-key failure to a friendly passphrase
            // validation so the GUI highlights the passphrase field rather than a
            // generic error banner.  nebula reports e.g.
            // "error while parsing encrypted ca-key: invalid passphrase or corrupt
            // private key".
            if (stripos($err, 'invalid passphrase') !== false || stripos($err, 'encrypted ca-key') !== false) {
                return ['result' => 'failed', 'validations' => ['passphrase' => 'invalid CA passphrase']];
            }
            return ['result' => 'failed', 'error' => $err];
        }

        // In generate-here mode the key must be present.
        if ($public_key === '' && empty($res['key'])) {
            return ['result' => 'failed', 'error' => 'configd returned no private key'];
        }

        // Store the signed certificate in the model.
        $node = $mdl->pki->certificates->certificate->Add();
        $node->descr           = $descr;
        $node->origin          = 'signed';
        $node->caref           = $caref;
        $node->crt             = $res['crt'];
        $node->key             = $res['key'] ?? '';
        $node->networks        = $networks;
        $node->groups          = $groups;
        $node->unsafe_networks = $unsafe_networks;
        $node->has_key         = ($res['key'] !== '') ? '1' : '0';

        // Parse the freshly signed cert once and store its computed fields
        // (including issuer = the signing CA's fingerprint, uniform with import).
        // The CA's display name is resolved dynamically at render from issuer,
        // so we do NOT store ca_name here.
        $printRes = $this->callPki('pki_print_cert', ['crt' => $res['crt']]);
        if (empty($printRes['error']) && !empty($printRes['info'])) {
            $this->storeComputedFields($node, $printRes);
        }

        return $this->saveCertNode($mdl, $node);
    }

    // -------------------------------------------------------------------------
    // POST /api/nebula/certificate/import
    // -------------------------------------------------------------------------

    /**
     * Validate a PEM-encoded Nebula private key string.
     *
     * Accepts any "-----BEGIN NEBULA ... PRIVATE KEY-----" header (ED25519,
     * X25519, P256 variants).  Rejects encrypted keys (header contains
     * "ENCRYPTED") and anything that does not look like a Nebula key PEM.
     *
     * Returns null on success, or a user-facing error string on failure.
     */
    private function validateKeyPem(string $key): ?string
    {
        // Must contain a NEBULA * PRIVATE KEY PEM header.
        if (!preg_match('/-----BEGIN NEBULA [A-Z0-9 ]*PRIVATE KEY-----/', $key)) {
            return 'not a valid Nebula private key';
        }
        // Encrypted keys are not supported (we store keys in plain config).
        if (stripos($key, 'ENCRYPTED') !== false) {
            return 'encrypted keys are not supported';
        }
        return null;
    }

    /**
     * Import a pre-existing Nebula host certificate.
     *
     * Request body (JSON or form-encoded):
     *   descr  string  required  Human-readable label
     *   crt    string  required  PEM-encoded Nebula host certificate
     *   key    string  optional  PEM-encoded private key
     *
     * The crt is validated via pki_print_cert before storage.  If the cert
     * prints cleanly the networks/groups from the cert details are stored back
     * into the model fields for display convenience.  The signing CA is NOT
     * selected by the user — it is derived from the cert's issuer fingerprint
     * (stored by storeComputedFields) and resolved to a name at render time.
     *
     * Returns:
     *   {"result":"saved","uuid":"<uuid>"}
     *   {"result":"failed","validations":{"crt":"..."}}
     *   {"result":"failed","validations":{"descr":"..."}}
     *   {"result":"failed","validations":{"key":"..."}}
     */
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

        // Validate the cert by calling pki_print_cert — rejects garbage.
        $printRes = $this->callPki('pki_print_cert', ['crt' => $crt]);

        if (!empty($printRes['error']) || empty($printRes['info'])) {
            return [
                'result'      => 'failed',
                'validations' => ['crt' => 'not a valid Nebula certificate'],
            ];
        }

        // Validate the private key if one was supplied.
        if ($key !== '') {
            $keyErr = $this->validateKeyPem($key);
            if ($keyErr !== null) {
                return ['result' => 'failed', 'validations' => ['key' => $keyErr]];
            }
        }

        // Extract networks/groups/unsafe_networks from the print output if available.
        // nebula-cert print -json returns an array; cert details are at [0].details.
        $details              = $printRes['info'][0]['details'] ?? [];
        $cert_networks        = '';
        $cert_groups          = '';
        $cert_unsafe_networks = '';
        if (!empty($details['networks'])) {
            $cert_networks = is_array($details['networks'])
                ? implode(',', $details['networks'])
                : (string)$details['networks'];
        }
        if (!empty($details['groups'])) {
            $cert_groups = is_array($details['groups'])
                ? implode(',', $details['groups'])
                : (string)$details['groups'];
        }
        if (!empty($details['unsafeNetworks'])) {
            $cert_unsafe_networks = is_array($details['unsafeNetworks'])
                ? implode(',', $details['unsafeNetworks'])
                : (string)$details['unsafeNetworks'];
        }

        $mdl  = $this->getModel();
        $node = $mdl->pki->certificates->certificate->Add();
        $node->descr           = $descr;
        $node->origin          = 'imported';
        $node->crt             = $crt;
        $node->networks        = $cert_networks;
        $node->groups          = $cert_groups;
        $node->unsafe_networks = $cert_unsafe_networks;

        if ($key !== '') {
            $node->key     = $key;
            $node->has_key = '1';
        } else {
            $node->has_key = '0';
        }

        // Store computed fields from the print output we already obtained above.
        $this->storeComputedFields($node, $printRes);

        return $this->saveCertNode($mdl, $node);
    }

    // -------------------------------------------------------------------------
    // GET /api/nebula/certificate/info/<uuid>
    // -------------------------------------------------------------------------

    /**
     * Return parsed certificate details for the GUI details view.
     *
     * Returns:
     *   {"info": [...]}   the array returned by pki_print_cert
     *   {"result":"failed","error":"..."}  if uuid unknown or cert invalid
     */
    public function infoAction($uuid = null)
    {
        if (empty($uuid)) {
            return ['result' => 'failed', 'error' => 'uuid is required'];
        }

        $mdl  = $this->getModel();
        $node = null;
        foreach ($mdl->pki->certificates->certificate->iterateItems() as $item) {
            if ($item->getAttribute('uuid') === $uuid) {
                $node = $item;
                break;
            }
        }

        if ($node === null) {
            return ['result' => 'failed', 'error' => 'certificate not found'];
        }

        $crt = (string)$node->crt;
        if ($crt === '') {
            return ['result' => 'failed', 'error' => 'certificate has no crt data'];
        }

        $printRes = $this->callPki('pki_print_cert', ['crt' => $crt]);

        if (!empty($printRes['error']) || empty($printRes['info'])) {
            $err = $printRes['error'] ?? 'pki_print_cert returned no info';
            return ['result' => 'failed', 'error' => $err];
        }

        return ['info' => $printRes['info']];
    }
}
