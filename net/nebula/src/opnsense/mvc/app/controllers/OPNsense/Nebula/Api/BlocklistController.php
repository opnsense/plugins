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
use OPNsense\Core\Config;

/**
 * Certificate blocklist API — fingerprints this node refuses to talk to.
 *
 * Each entry blocks one certificate by its sha256 fingerprint.  Scope is
 * explicit: a `scope` of `global` applies the block to every instance, while
 * `instance` scopes it to the single instance named in `instance` (required in
 * that case).  The fingerprint of an existing entry is immutable (changing it
 * would silently re-target the block), and every fingerprint must be 64
 * lowercase hex characters (sha256).  The grid cross-references each fingerprint
 * against the configured certificates so a blocked local cert is named.
 *
 * Endpoints:
 *   GET  /api/nebula/blocklist/search_item[?instance=<uuid>]
 *   GET  /api/nebula/blocklist/get_item/<uuid>
 *   POST /api/nebula/blocklist/add_item
 *   POST /api/nebula/blocklist/set_item/<uuid>
 *   POST /api/nebula/blocklist/del_item/<uuid>
 *   POST /api/nebula/blocklist/toggle_item/<uuid>[/<enabled>]
 *   POST /api/nebula/blocklist/block_cert/<certref> — block a cert globally
 *
 * @package OPNsense\Nebula\Api
 */
class BlocklistController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'nebula';
    protected static $internalModelClass = 'OPNsense\Nebula\Nebula';

    // -------------------------------------------------------------------------
    // Fingerprint validation
    // -------------------------------------------------------------------------

    /**
     * Validate the posted entry's fingerprint as a 64-char lowercase hex sha256.
     *
     * The form posts the entry fields nested under the "entry" key, e.g.
     * {"entry": {"fingerprint": "abc...", ...}} — so we read from there, mirroring
     * FirewallRuleController's checkCaReferences (which validates ca_sha the same
     * way).  Returns null when valid, or a validations error array on failure.
     *
     * @return array|null  null = valid; array = ['result'=>'failed','validations'=>[...]]
     */
    private function checkFingerprintFormat(): ?array
    {
        $entry = $this->request->getPost('entry');
        if (!is_array($entry)) {
            $entry = [];
        }
        $fp = isset($entry['fingerprint']) ? trim((string)$entry['fingerprint']) : '';
        if (!preg_match('/^[0-9a-f]{64}$/', $fp)) {
            return [
                'result'      => 'failed',
                'validations' => [
                    'entry.fingerprint' =>
                        'Fingerprint must be 64 lowercase hex characters (sha256).',
                ],
            ];
        }
        return null;
    }

    /**
     * Reject an attempt to change the fingerprint of an existing entry.
     *
     * The fingerprint is the entry's identity; mutating it on an edit would
     * silently re-point the block at a different certificate.  We compare the
     * posted fingerprint against the value stored on the entry being edited and
     * fail with a validation error keyed on entry.fingerprint when they differ.
     *
     * @param string $uuid uuid of the entry being edited
     * @return array|null  null = unchanged/ok; array = ['result'=>'failed', ...]
     */
    private function checkFingerprintImmutable(string $uuid): ?array
    {
        $entry = $this->request->getPost('entry');
        if (!is_array($entry)) {
            $entry = [];
        }
        $posted = isset($entry['fingerprint']) ? trim((string)$entry['fingerprint']) : '';

        $stored = null;
        foreach ($this->getModel()->pki->blocklist->entry->iterateItems() as $node) {
            if ($node->getAttribute('uuid') === $uuid) {
                $stored = trim((string)$node->fingerprint);
                break;
            }
        }

        if ($stored !== null && $posted !== $stored) {
            return [
                'result'      => 'failed',
                'validations' => [
                    'entry.fingerprint' =>
                        'Fingerprint cannot be changed on an existing blocklist entry. ' .
                        'Delete this entry and add a new one for a different certificate.',
                ],
            ];
        }
        return null;
    }

    /**
     * When scope is "instance", an instance must be chosen.  A global block needs
     * no instance.  Keeps the explicit-scope model honest (no instance-scoped
     * entry that silently behaves global because its instance was left blank).
     *
     * @return array|null  null = valid; array = ['result'=>'failed', ...]
     */
    private function checkScope(): ?array
    {
        $entry = $this->request->getPost('entry');
        if (!is_array($entry)) {
            $entry = [];
        }
        $scope = isset($entry['scope']) ? (string)$entry['scope'] : 'global';
        if ($scope === 'instance') {
            $inst = isset($entry['instance']) ? trim((string)$entry['instance']) : '';
            if ($inst === '') {
                return [
                    'result'      => 'failed',
                    'validations' => [
                        'entry.instance' =>
                            'Select an instance, or set Scope to Global.',
                    ],
                ];
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Standard CRUD
    // -------------------------------------------------------------------------

    /**
     * Search blocklist entries, optionally scoped to one instance.
     *
     * Pass ?instance=<uuid> to restrict the result set to entries that apply to
     * that instance: global entries (instance == '') OR entries whose instance
     * field equals the given uuid.  Without the parameter every entry is
     * returned.  The instance uuid is resolved to its description for display
     * ("global" when empty), mirroring InstanceController's certref resolution.
     */
    public function searchItemAction()
    {
        $instanceUuid = trim($this->request->get('instance', 'string', ''));

        $filter_funct = null;
        if ($instanceUuid === '__global__') {
            // "Global" filter: only the global blocklist (no instance-scoped rows).
            $filter_funct = function ($record) {
                return (string)$record->scope !== 'instance';
            };
        } elseif ($instanceUuid !== '') {
            // A specific instance: the entries that apply to it — Global ones plus
            // those explicitly scoped to it (the concatenated view it renders).
            $filter_funct = function ($record) use ($instanceUuid) {
                if ((string)$record->scope === 'instance') {
                    return (string)$record->instance === $instanceUuid;
                }
                return true;
            };
        }

        $result = $this->searchBase(
            'pki.blocklist.entry',
            ['enabled', 'scope', 'instance', 'fingerprint', 'descr', 'expiry'],
            null,
            $filter_funct
        );

        if (!empty($result['rows']) && is_array($result['rows'])) {
            // Resolve the "Applies to" column: a Global entry renders as
            // "Global (all instances)"; an instance-scoped entry renders the
            // instance description ("(deleted)" if the uuid is dangling).
            $instByUuid = [];
            foreach ($this->getModel()->instances->instance->iterateItems() as $inst) {
                $instByUuid[$inst->getAttribute('uuid')] = (string)$inst->description;
            }
            // Cross-reference fingerprints against configured certificates so a
            // blocked local cert is named in the grid (foreign fingerprints stay
            // blank — they belong to peers whose certs we do not hold).
            $certByFp = [];
            foreach ($this->getModel()->pki->certificates->certificate->iterateItems() as $crt) {
                $fp = strtolower(trim((string)$crt->fingerprint));
                if ($fp !== '') {
                    $certByFp[$fp] = (string)$crt->descr;
                }
            }
            foreach ($result['rows'] as &$row) {
                // Overwrite the raw instance uuid with the "Applies to" display
                // (the grid's instance column is labelled "Applies to").
                if ((string)($row['scope'] ?? '') === 'instance') {
                    $uuid = trim((string)($row['instance'] ?? ''));
                    $row['instance'] = array_key_exists($uuid, $instByUuid)
                        ? $instByUuid[$uuid] : '(deleted)';
                } else {
                    $row['instance'] = gettext('Global (all instances)');
                }
                // Single "Certificate" column: resolve the fingerprint to a cert
                // name when we hold it, else "unknown", always suffixed with the
                // first 8 hex of the fingerprint to disambiguate like-named certs
                // ("name: 0123abcd" / "unknown: 0123abcd"). The raw 64-char column
                // is hidden in the dialog grid_view in favour of this.
                $fp = strtolower(trim((string)($row['fingerprint'] ?? '')));
                if ($fp === '') {
                    $row['certificate'] = '';
                } else {
                    $short = substr($fp, 0, 8);
                    $name = $certByFp[$fp] ?? gettext('unknown');
                    $row['certificate'] = sprintf('%s: %s', $name, $short);
                }
            }
            unset($row);
        }
        return $result;
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase('entry', 'pki.blocklist.entry', $uuid);
    }

    /**
     * Add a new blocklist entry.
     *
     * The fingerprint must be 64 lowercase hex characters (sha256).
     */
    public function addItemAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        $fpErr = $this->checkFingerprintFormat();
        if ($fpErr !== null) {
            return $fpErr;
        }
        $scopeErr = $this->checkScope();
        if ($scopeErr !== null) {
            return $scopeErr;
        }
        return $this->addBase('entry', 'pki.blocklist.entry');
    }

    /**
     * Update an existing blocklist entry.
     *
     * The fingerprint is immutable (rejected if changed) and must remain a valid
     * 64-char lowercase hex sha256.
     */
    public function setItemAction($uuid = null)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        $immutErr = $this->checkFingerprintImmutable((string)$uuid);
        if ($immutErr !== null) {
            return $immutErr;
        }
        $fpErr = $this->checkFingerprintFormat();
        if ($fpErr !== null) {
            return $fpErr;
        }
        $scopeErr = $this->checkScope();
        if ($scopeErr !== null) {
            return $scopeErr;
        }
        return $this->setBase('entry', 'pki.blocklist.entry', $uuid);
    }

    public function delItemAction($uuid = null)
    {
        return $this->delBase('pki.blocklist.entry', $uuid);
    }

    public function toggleItemAction($uuid = null, $enabled = null)
    {
        return $this->toggleBase('pki.blocklist.entry', $uuid, $enabled);
    }

    // -------------------------------------------------------------------------
    // POST /api/nebula/blocklist/block_cert/<certref>
    // -------------------------------------------------------------------------

    /**
     * Block a certificate globally by its certref.
     *
     * Looks up the certificate's fingerprint and expiry (valid_to) and adds a
     * GLOBAL blocklist entry (empty instance) prefilled from them.  Idempotent:
     * if that fingerprint is already globally blocked nothing is added and the
     * call still reports success, so the "Block" button on the certificates page
     * can be pressed more than once without creating duplicates.
     *
     * Returns:
     *   {"result":"saved","uuid":"<uuid>"}        a new global block was created
     *   {"result":"exists","uuid":"<uuid>"}       already globally blocked
     *   {"result":"failed","error":"..."}         certref unknown / no fingerprint
     */
    public function blockCertAction($certref = null)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'error' => 'POST required'];
        }
        if (empty($certref)) {
            return ['result' => 'failed', 'error' => 'certref is required'];
        }

        $mdl = $this->getModel();

        // Resolve the certificate node and pull its fingerprint + expiry.
        $cert = null;
        foreach ($mdl->pki->certificates->certificate->iterateItems() as $item) {
            if ($item->getAttribute('uuid') === $certref) {
                $cert = $item;
                break;
            }
        }
        if ($cert === null) {
            return ['result' => 'failed', 'error' => 'certificate not found'];
        }

        $fingerprint = trim((string)$cert->fingerprint);
        if (!preg_match('/^[0-9a-f]{64}$/', $fingerprint)) {
            return ['result' => 'failed', 'error' => 'certificate has no usable fingerprint'];
        }
        // valid_to is stored as the cert's notAfter; copy it as the block expiry
        // so a block for an expired cert is itself dropped at render time.
        $expiry = trim((string)$cert->valid_to);

        // Idempotency: skip if this fingerprint is already globally blocked.
        foreach ($mdl->pki->blocklist->entry->iterateItems() as $entry) {
            if (
                (string)$entry->scope !== 'instance' &&
                trim((string)$entry->fingerprint) === $fingerprint
            ) {
                return ['result' => 'exists', 'uuid' => $entry->getAttribute('uuid')];
            }
        }

        // Create the global block (explicit global scope).
        $node = $mdl->pki->blocklist->entry->Add();
        $node->enabled     = '1';
        $node->scope       = 'global';
        $node->instance    = '';
        $node->fingerprint = $fingerprint;
        $node->expiry      = $expiry;
        $node->certref     = $certref;
        $node->descr       = sprintf('Blocked: %s', (string)$cert->descr);

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

        return ['result' => 'saved', 'uuid' => $node->getAttribute('uuid')];
    }

    // -------------------------------------------------------------------------
    // POST /api/nebula/blocklist/import
    // -------------------------------------------------------------------------

    /**
     * Bulk-import blocklist entries from a textarea — one fingerprint per line,
     * optionally "fingerprint, description" (or "fingerprint description"). Each
     * fingerprint is normalised to bare lowercase hex (colons/spaces stripped)
     * and must be 64 hex chars. Lines that don't yield a valid fingerprint are
     * reported in `invalid` and skipped; a fingerprint already blocked at the
     * same scope is counted in `skipped`. All accepted entries are created at the
     * chosen scope (global, or a single instance).
     *
     * Returns {result:'saved', added:int, skipped:int, invalid:[...]} or a
     * failed-validation payload.
     */
    public function importAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }

        $scope = $this->request->getPost('scope', 'string', 'global');
        $instance = trim((string)$this->request->getPost('instance', 'string', ''));
        $defaultDescr = trim((string)$this->request->getPost('descr', 'string', ''));
        $raw = (string)$this->request->getPost('fingerprints', 'string', '');

        if ($scope === 'instance' && $instance === '') {
            return [
                'result'      => 'failed',
                'validations' => ['import.instance' => 'Select an instance, or set Scope to Global.'],
            ];
        }
        if ($scope !== 'instance') {
            $scope = 'global';
            $instance = '';
        }

        $mdl = $this->getModel();

        // Existing entries at this same scope, keyed by fingerprint, so a re-import
        // is idempotent (already-blocked fingerprints are skipped, not duplicated).
        $existing = [];
        foreach ($mdl->pki->blocklist->entry->iterateItems() as $e) {
            $eScope = (string)$e->scope === 'instance' ? 'instance' : 'global';
            $eInst  = $eScope === 'instance' ? (string)$e->instance : '';
            if ($eScope === $scope && $eInst === $instance) {
                $existing[strtolower(trim((string)$e->fingerprint))] = true;
            }
        }

        $added = 0;
        $skipped = 0;
        $invalid = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Split off the first token (the fingerprint); the remainder, if any,
            // is a per-line description that overrides the dialog default.
            $parts = preg_split('/[\s,]+/', $line, 2);
            $fp = strtolower(preg_replace('/[^0-9a-f]/i', '', $parts[0]));
            $lineDescr = (count($parts) > 1) ? trim($parts[1]) : $defaultDescr;
            if (!preg_match('/^[0-9a-f]{64}$/', $fp)) {
                $invalid[] = $parts[0];
                continue;
            }
            if (isset($existing[$fp])) {
                $skipped++;
                continue;
            }
            $node = $mdl->pki->blocklist->entry->Add();
            $node->enabled     = '1';
            $node->scope       = $scope;
            $node->instance    = $instance;
            $node->fingerprint = $fp;
            $node->descr       = $lineDescr;
            $existing[$fp] = true;
            $added++;
        }

        if ($added > 0) {
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
            'result'  => 'saved',
            'added'   => $added,
            'skipped' => $skipped,
            'invalid' => $invalid,
        ];
    }

    // -------------------------------------------------------------------------
    // POST /api/nebula/blocklist/purge_expired
    // -------------------------------------------------------------------------

    /**
     * Delete every blocklist entry whose Expiry date has passed. The selection/
     * deletion lives on the model (Nebula::purgeExpiredBlocklist); this action
     * just persists and reports. Blocklist entries are referenced by nothing, so
     * none are skipped — and this is the ONLY thing that removes an expired block
     * (the renderer keeps blocking until the entry is purged).
     *
     * Returns {"result":"saved","removed":N}.
     */
    public function purgeExpiredAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'error' => 'POST required'];
        }

        $mdl = $this->getModel();
        $res = $mdl->purgeExpiredBlocklist();

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

        return ['result' => 'saved', 'removed' => $res['removed']];
    }
}
