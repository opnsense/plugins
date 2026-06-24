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
 * Firewall rule API — per-instance, identity-aware host firewall rules.
 *
 * Each rule targets one Nebula instance and may match traffic by host IP,
 * certificate groups, CIDR, CA name, or CA fingerprint (sha).  At least one
 * matcher field (host / groups / cidr / ca_name / ca_sha) must be non-empty
 * for the rule to be valid.
 *
 * Endpoints:
 *   GET  /api/nebula/firewall_rule/search_item[?instance=<uuid>]
 *   GET  /api/nebula/firewall_rule/get_item/<uuid>
 *   POST /api/nebula/firewall_rule/add_item
 *   POST /api/nebula/firewall_rule/set_item/<uuid>
 *   POST /api/nebula/firewall_rule/del_item/<uuid>
 *   POST /api/nebula/firewall_rule/toggle_item/<uuid>[/<enabled>]
 *
 * @package OPNsense\Nebula\Api
 */
class FirewallRuleController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'nebula';
    protected static $internalModelClass = 'OPNsense\Nebula\Nebula';

    // -------------------------------------------------------------------------
    // Matcher-field validation
    // -------------------------------------------------------------------------

    /**
     * The set of identity-matcher fields.  At least one must be non-empty on
     * every rule (validated in addItemAction / setItemAction before save).
     */
    private static $matcherFields = ['host', 'groups', 'cidr', 'ca_name', 'ca_sha'];

    /**
     * Return a validations error array when the posted data has no matcher, or
     * null when at least one matcher field is populated.
     *
     * @return array|null  null = valid; array = ['validations' => [...]]
     */
    private function checkMatcherPresent(): ?array
    {
        // The form posts the rule fields nested under the "rule" key, e.g.
        // {"rule": {"host": "any", "group": "", ...}} — so we must read from
        // there, NOT getPost('host'). A matcher counts as present when it is
        // non-empty; `host: any` is the legitimate "match every host" choice
        // and must satisfy the requirement.
        $rule = $this->request->getPost('rule');
        if (!is_array($rule)) {
            $rule = [];
        }
        foreach (self::$matcherFields as $f) {
            if (isset($rule[$f]) && trim((string)$rule[$f]) !== '') {
                return null;
            }
        }
        // Key the error with the model prefix (rule.<field>) on EVERY matcher so
        // the dialog highlights the whole group (it's a "fill at least one" set).
        $msg = 'Fill at least one matcher: host, groups, cidr, ca_name, or ca_sha.';
        $validations = [];
        foreach (self::$matcherFields as $f) {
            $validations['rule.' . $f] = $msg;
        }
        return ['result' => 'failed', 'validations' => $validations];
    }

    /**
     * Set of Nebula CA names (the cert-embedded `cn`) for every configured
     * authority.  Used for ca_name referential integrity: a rule's ca_name
     * matcher only makes sense when it names a CA this node actually trusts —
     * a peer can only connect if its certificate chains to one of these.
     *
     * @return array<string,bool>  CA name => true
     */
    private function configuredCaNames(): array
    {
        $names = [];
        foreach ($this->getModel()->pki->authorities->authority->iterateItems() as $auth) {
            $cn = trim((string)$auth->cn);
            if ($cn !== '') {
                $names[$cn] = true;
            }
        }
        return $names;
    }

    /**
     * Validate the CA matchers on the posted rule:
     *   - ca_name, when set, must name a configured CA (referential integrity);
     *   - ca_sha, when set, must be a 64-char lowercase hex sha256 fingerprint.
     *
     * @return array|null  null = valid; array = ['validations' => [...]]
     */
    private function checkCaReferences(): ?array
    {
        $rule = $this->request->getPost('rule');
        if (!is_array($rule)) {
            $rule = [];
        }
        $validations = [];

        $caName = isset($rule['ca_name']) ? trim((string)$rule['ca_name']) : '';
        if ($caName !== '') {
            $known = $this->configuredCaNames();
            if (!isset($known[$caName])) {
                $validations['rule.ca_name'] = sprintf(
                    'No configured CA named "%s". Pick one from the list, or add the CA under Authorities.',
                    $caName
                );
            }
        }

        $caSha = isset($rule['ca_sha']) ? trim((string)$rule['ca_sha']) : '';
        if ($caSha !== '' && !preg_match('/^[0-9a-f]{64}$/', $caSha)) {
            $validations['rule.ca_sha'] =
                'CA fingerprint must be 64 lowercase hex characters (sha256).';
        }

        return empty($validations) ? null : ['result' => 'failed', 'validations' => $validations];
    }

    // -------------------------------------------------------------------------
    // Standard CRUD
    // -------------------------------------------------------------------------

    /**
     * Search firewall rules, optionally scoped to one instance.
     *
     * Pass ?instance=<uuid> to restrict the result set to rules whose
     * `instance` field equals the given UUID.  Without the parameter all
     * rules are returned.
     */
    public function searchItemAction()
    {
        $instanceUuid = trim($this->request->get('instance', 'string', ''));

        $filter_funct = null;
        if ($instanceUuid !== '') {
            $filter_funct = function ($record) use ($instanceUuid) {
                return (string)$record->instance === $instanceUuid;
            };
        }

        return $this->searchBase(
            'fwrules.rule',
            [
                'enabled', 'instance', 'direction', 'protocol', 'port', 'description',
                // matcher fields — surfaced so the grid can render a "Match" summary
                'host', 'groups', 'cidr', 'local_cidr', 'ca_name', 'ca_sha',
            ],
            null,
            $filter_funct
        );
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase('rule', 'fwrules.rule', $uuid);
    }

    /**
     * Add a new firewall rule.
     *
     * Requires at least one matcher field (host/groups/cidr/ca_name/ca_sha).
     */
    public function addItemAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        $matcherErr = $this->checkMatcherPresent();
        if ($matcherErr !== null) {
            return $matcherErr;
        }
        $caErr = $this->checkCaReferences();
        if ($caErr !== null) {
            return $caErr;
        }
        return $this->addBase('rule', 'fwrules.rule');
    }

    /**
     * Update an existing firewall rule.
     *
     * Requires at least one matcher field (host/groups/cidr/ca_name/ca_sha).
     */
    public function setItemAction($uuid = null)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        $matcherErr = $this->checkMatcherPresent();
        if ($matcherErr !== null) {
            return $matcherErr;
        }
        $caErr = $this->checkCaReferences();
        if ($caErr !== null) {
            return $caErr;
        }
        return $this->setBase('rule', 'fwrules.rule', $uuid);
    }

    public function delItemAction($uuid = null)
    {
        return $this->delBase('fwrules.rule', $uuid);
    }

    public function toggleItemAction($uuid = null, $enabled = null)
    {
        return $this->toggleBase('fwrules.rule', $uuid, $enabled);
    }
}
