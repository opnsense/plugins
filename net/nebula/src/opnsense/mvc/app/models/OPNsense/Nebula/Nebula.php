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

namespace OPNsense\Nebula;

use OPNsense\Base\BaseModel;

class Nebula extends BaseModel
{
    /**
     * Subsystem-dirty marker — the legacy is_subsystem_dirty('nebula') idiom
     * (same mechanism IPsec uses with /tmp/ipsec.dirty). Touched whenever the
     * model persists a change, removed by ServiceController on a successful
     * reconfigure, and reported via /api/nebula/service/dirty so the "apply
     * needed" banner survives page reloads and cross-page navigation.
     */
    public const RECONFIGURE_MARKER = '/tmp/nebula.dirty';

    /**
     * Persist the model, then mark the Nebula subsystem dirty so the apply
     * banner is shown until the user reconfigures. Single choke point: every
     * CRUD save and every PKI custom action (generate/sign/import) serializes
     * through here, so none can change config without flagging an apply.
     *
     * @return bool whether changes were persisted
     */
    public function serializeToConfig($validateFullModel = false, $disable_validation = false)
    {
        $this->assignDeviceNames();
        $persisted = parent::serializeToConfig($validateFullModel, $disable_validation);
        if ($persisted) {
            @touch(self::RECONFIGURE_MARKER);
        }
        return $persisted;
    }

    /**
     * Ensure every instance has a concrete, stable tun device name BEFORE the
     * config is written. An instance with an empty tun_name gets
     * "nebula" + the first 6 hex of md5(uuid) — derived from the instance's
     * immutable, unique UUID, so the name is unique by construction and never
     * reused by a different instance (a freed name cannot come back, which would
     * otherwise let a new instance inherit firewall/gateway settings bound to the
     * old assignment). md5 is uniformly distributed regardless of UUID
     * version/bits (same technique as sshdPortFor()). The realif name is
     * therefore stable for the instance's life; deleting one instance never
     * renumbers another. Names already set (typed by the admin, or pre-existing)
     * are left untouched. On the ≈impossible md5-prefix collision, widen the hex
     * slice up to the 15-char IFNAMSIZ limit ("nebula" + 9 hex).
     */
    public function assignDeviceNames(): void
    {
        $used = [];
        foreach ($this->instances->instance->iterateItems() as $node) {
            $name = trim((string)$node->tun_name);
            if ($name !== '') {
                $used[$name] = true;
            }
        }
        foreach ($this->instances->instance->iterateItems() as $node) {
            if (trim((string)$node->tun_name) !== '') {
                continue;
            }
            $digest = md5((string)$node->getAttribute('uuid'));
            $len = 6;
            $name = 'nebula' . substr($digest, 0, $len);
            while (isset($used[$name]) && $len < 9) {
                $len++;
                $name = 'nebula' . substr($digest, 0, $len);
            }
            $node->tun_name = $name;
            $used[$name] = true;
        }
    }

    /**
     * Generate a nebula YAML config for the given instance node, driven by the
     * scalar field -> yaml-path map in ConfigMap.php. The fixed pki: block (per
     * uuid cert paths) and the minimal permissive firewall: rule lists are
     * merged on top (Plan 5 replaces the rule lists with the per-rule grid).
     *
     * The PHP CLI on the appliance has no yaml extension, so YAML is hand-emitted.
     *
     * @param \OPNsense\Base\FieldTypes\ArrayField $instanceNode an instance from instances->instance
     * @return string YAML configuration text
     */
    public function generateConfig($instanceNode, ?string $diagPubKey = null): string
    {
        $map = require __DIR__ . '/ConfigMap.php';

        $cfg = [];
        foreach ($map as $field => $spec) {
            $v = (string)$instanceNode->$field;
            if ($v === '') {
                // Unset optional (e.g. read_buffer) -> omit from output.
                continue;
            }
            switch ($spec['type']) {
                case 'bool':
                    $val = ($v === '1');
                    break;
                case 'int':
                    $val = (int)$v;
                    break;
                case 'list':
                    // Split on newlines and commas, trim each token, drop empties.
                    $items = array_values(array_filter(
                        array_map('trim', preg_split('/[\n,]+/', $v)),
                        fn($s) => $s !== ''
                    ));
                    if (empty($items)) {
                        // Omit the key entirely when the list is empty.
                        continue 2;
                    }
                    $val = $items;
                    break;
                default:
                    $val = $v;
                    break;
            }
            $this->assignPath($cfg, $spec['yaml'], $val);
        }

        // Fixed pki: block, keyed by the instance uuid.
        $uuid = $instanceNode->getAttribute('uuid');
        $certDir = '/usr/local/etc/nebula/' . $uuid;
        $cfg['pki'] = [
            'ca' => $certDir . '/ca.crt',
            'cert' => $certDir . '/host.crt',
            'key' => $certDir . '/host.key',
        ];

        // pki.blocklist: certificate fingerprints this node refuses to talk to.
        // An entry applies when it is enabled and in scope (Global, or scoped to
        // this exact instance uuid). The block stays in effect regardless of the
        // entry's `expiry`: expiry is only a purge-eligibility date for the
        // "Purge expired" button (block-until-purged), not an effective end date,
        // so it is intentionally NOT consulted here.
        // The key is omitted entirely when no entry is in effect so a node with no
        // blocklist emits no blocklist: key (matching nebula's optional schema).
        $blocklist = [];
        foreach ($this->pki->blocklist->entry->iterateItems() as $entry) {
            if ((string)$entry->enabled !== '1') {
                continue;
            }
            // 'instance'-scoped entries apply only to their own instance; Global
            // entries apply to every node. Scope is explicit (see model comment).
            if ((string)$entry->scope === 'instance' && (string)$entry->instance !== $uuid) {
                continue;
            }
            $fp = trim((string)$entry->fingerprint);
            if ($fp !== '') {
                $blocklist[] = $fp;
            }
        }
        if (!empty($blocklist)) {
            $cfg['pki']['blocklist'] = $blocklist;
        }

        // Nebula is deny-by-default: a node with no firewall rules passes zero
        // traffic and `nebula -test` rejects a config without a firewall block.
        // The firewall scalar knobs (outbound_action/inbound_action/...) and the
        // nested conntrack map were already placed under firewall by the map walk;
        // build the inbound/outbound rule lists from the per-instance fwrules grid.
        if (!isset($cfg['firewall']) || !is_array($cfg['firewall'])) {
            $cfg['firewall'] = [];
        }

        // Permissive fallback used when a direction has no enabled rules.
        $permissive = [['port' => 'any', 'proto' => 'any', 'host' => 'any']];

        // Scalar matcher fields emitted as-is when non-empty.
        $scalarMatchers = ['host', 'cidr', 'local_cidr', 'ca_name', 'ca_sha'];

        $inbound = [];
        $outbound = [];
        foreach ($this->fwrules->rule->iterateItems() as $rule) {
            if ((string)$rule->instance !== $uuid) {
                continue;
            }
            if ((string)$rule->enabled !== '1') {
                continue;
            }

            $map = [
                'port'  => (string)$rule->port  !== '' ? (string)$rule->port  : 'any',
                'proto' => (string)$rule->protocol !== '' ? (string)$rule->protocol : 'any',
            ];

            // Scalar matchers: include only when non-empty.
            foreach ($scalarMatchers as $f) {
                $v = (string)$rule->$f;
                if ($v !== '') {
                    $map[$f] = $v;
                }
            }

            // groups: stored as comma/newline-separated; render as a YAML list.
            $groupsRaw = (string)$rule->groups;
            if ($groupsRaw !== '') {
                $items = array_values(array_filter(
                    array_map('trim', preg_split('/[\n,]+/', $groupsRaw)),
                    fn($s) => $s !== ''
                ));
                if (!empty($items)) {
                    $map['groups'] = $items;
                }
            }

            $direction = (string)$rule->direction;
            if ($direction === 'outbound') {
                $outbound[] = $map;
            } else {
                $inbound[] = $map;
            }
        }

        $cfg['firewall']['outbound'] = empty($outbound) ? $permissive : $outbound;
        $cfg['firewall']['inbound']  = empty($inbound)  ? $permissive : $inbound;

        // static_host_map: a map of nebula-IP => list-of-underlay-addresses, built
        // from this instance's enabled static_hostmap grid entries (one nebula_ip
        // -> comma/newline-separated addresses per row). Addresses are
        // de-duplicated per overlay IP; the key is omitted when there are none.
        $shm = [];
        foreach ($this->static_hostmap->entry->iterateItems() as $e) {
            if ((string)$e->instance !== $uuid || (string)$e->enabled !== '1') {
                continue;
            }
            $overlayIp = trim((string)$e->nebula_ip);
            if ($overlayIp === '') {
                continue;
            }
            foreach (preg_split('/[\n,]+/', (string)$e->addresses) as $a) {
                $a = trim($a);
                if ($a === '') {
                    continue;
                }
                if (!isset($shm[$overlayIp])) {
                    $shm[$overlayIp] = [];
                }
                if (!in_array($a, $shm[$overlayIp], true)) {
                    $shm[$overlayIp][] = $a;
                }
            }
        }
        if (!empty($shm)) {
            $cfg['static_host_map'] = $shm;
        }

        // tun.unsafe_routes: route non-Nebula subnets over the overlay via a
        // Nebula peer. Built from the per-instance unsafe-routes grid; each
        // enabled route emits {route, via, [mtu], [metric], install}.
        $unsafe = [];
        foreach ($this->unsafe_routes->route->iterateItems() as $r) {
            if ((string)$r->instance !== $uuid || (string)$r->enabled !== '1') {
                continue;
            }
            $route = trim((string)$r->route);
            $via   = trim((string)$r->via);
            if ($route === '' || $via === '') {
                continue;
            }
            $entry = ['route' => $route, 'via' => $via];
            $mtu = trim((string)$r->mtu);
            if ($mtu !== '') {
                $entry['mtu'] = (int)$mtu;
            }
            $metric = trim((string)$r->metric);
            if ($metric !== '') {
                $entry['metric'] = (int)$metric;
            }
            // install defaults true in nebula; emit it explicitly so the routing
            // intent is unambiguous in the rendered config.
            $entry['install'] = ((string)$r->install === '1');
            $unsafe[] = $entry;
        }
        if (!empty($unsafe)) {
            if (!isset($cfg['tun']) || !is_array($cfg['tun'])) {
                $cfg['tun'] = [];
            }
            $cfg['tun']['unsafe_routes'] = $unsafe;
        }

        // tun.routes: per-route MTU overrides for Nebula overlay routes (the
        // node's own subnets). Each enabled route emits {route, mtu}.
        $tunRoutes = [];
        foreach ($this->tun_routes->route->iterateItems() as $r) {
            if ((string)$r->instance !== $uuid || (string)$r->enabled !== '1') {
                continue;
            }
            $route = trim((string)$r->route);
            $mtu   = trim((string)$r->mtu);
            if ($route === '' || $mtu === '') {
                continue;
            }
            $tunRoutes[] = ['route' => $route, 'mtu' => (int)$mtu];
        }
        if (!empty($tunRoutes)) {
            if (!isset($cfg['tun']) || !is_array($cfg['tun'])) {
                $cfg['tun'] = [];
            }
            $cfg['tun']['routes'] = $tunRoutes;
        }

        // sshd debug server: ALWAYS on — the plugin's internal management channel
        // behind the Status page (live peers, close/query tunnels, etc.). It is
        // not user-configurable: bound to 127.0.0.1 on a per-instance derived
        // port, with a per-instance host key and ONLY the plugin's diagnostics
        // key authorized (no user access). $diagPubKey is supplied by setup.php
        // at apply time; on a bare render (e.g. nebula -test) it may be absent,
        // in which case the block still carries enabled/listen/host_key and
        // setup.php injects the key before writing the live config.
        $cfg['sshd'] = [
            'enabled'  => true,
            'listen'   => '127.0.0.1:' . $this->sshdPortFor($uuid),
            'host_key' => $certDir . '/sshd_host_key',
        ];
        if (is_string($diagPubKey) && $diagPubKey !== '') {
            $cfg['sshd']['authorized_users'] = [
                ['user' => '_opnsense_diag', 'keys' => [$diagPubKey]],
            ];
        }

        // lighthouse allow-lists: CIDR-keyed maps merged into the lighthouse:
        // section (the lighthouse scalar knobs were placed by the map walk
        // above). Each is a hand-authored textarea, one entry per line; malformed
        // lines are skipped and left for `nebula -test` to surface. Emitted only
        // when non-empty so a node with no allow-lists adds no keys.
        $lighthouse = [];

        // remote_allow_list / local_allow_list: "CIDR = true|false" per line.
        // local_allow_list additionally accepts "interface <regex> = true|false",
        // which lands under a nested interfaces: map (nebula's special key).
        $remoteAllow = $this->parseAllowList((string)$instanceNode->lighthouse_remote_allow_list, false);
        if (!empty($remoteAllow)) {
            $lighthouse['remote_allow_list'] = $remoteAllow;
        }
        $localAllow = $this->parseAllowList((string)$instanceNode->lighthouse_local_allow_list, true);
        if (!empty($localAllow)) {
            $lighthouse['local_allow_list'] = $localAllow;
        }

        // remote_allow_ranges (experimental): "<vpn-cidr> <remote-cidr> = bool"
        // per line -> map<vpn-cidr, map<remote-cidr, bool>>.
        $ranges = [];
        foreach (explode("\n", (string)$instanceNode->lighthouse_remote_allow_ranges) as $line) {
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $cidrs = preg_split('/\s+/', trim(substr($line, 0, $eq)), -1, PREG_SPLIT_NO_EMPTY);
            $bool = $this->parseYamlBool(substr($line, $eq + 1));
            if ($bool === null || count($cidrs) !== 2) {
                continue;
            }
            [$vpn, $remote] = $cidrs;
            if (!isset($ranges[$vpn])) {
                $ranges[$vpn] = [];
            }
            $ranges[$vpn][$remote] = $bool;
        }
        if (!empty($ranges)) {
            $lighthouse['remote_allow_ranges'] = $ranges;
        }

        // calculated_remotes (experimental): "<vpn-cidr> <mask-cidr> <port>" per
        // line -> map<vpn-cidr, list<{mask, port}>>. Repeat the vpn-cidr for
        // multiple entries.
        $calc = [];
        foreach (explode("\n", (string)$instanceNode->lighthouse_calculated_remotes) as $line) {
            $tok = preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY);
            if (count($tok) !== 3 || !ctype_digit($tok[2])) {
                continue;
            }
            [$vpn, $mask, $port] = $tok;
            if (!isset($calc[$vpn])) {
                $calc[$vpn] = [];
            }
            $calc[$vpn][] = ['mask' => $mask, 'port' => (int)$port];
        }
        if (!empty($calc)) {
            $lighthouse['calculated_remotes'] = $calc;
        }

        if (!empty($lighthouse)) {
            if (!isset($cfg['lighthouse']) || !is_array($cfg['lighthouse'])) {
                $cfg['lighthouse'] = [];
            }
            $cfg['lighthouse'] = array_merge($cfg['lighthouse'], $lighthouse);
        }

        // stats: graphite/prometheus telemetry. Emitted only when a type is
        // chosen (blank = no telemetry). graphite and prometheus share interval
        // and the two metric toggles but otherwise have disjoint keys; we emit
        // only the keys relevant to the selected type so nebula sees a valid
        // sub-schema. Optional/empty sub-fields are omitted.
        $statsType = (string)$instanceNode->stats_type;
        if ($statsType === 'graphite' || $statsType === 'prometheus') {
            $stats = ['type' => $statsType];
            $interval = trim((string)$instanceNode->stats_interval);
            if ($interval !== '') {
                $stats['interval'] = $interval;
            }
            if ($statsType === 'graphite') {
                foreach (['prefix' => 'stats_prefix', 'protocol' => 'stats_protocol', 'host' => 'stats_host'] as $key => $field) {
                    $v = trim((string)$instanceNode->$field);
                    if ($v !== '') {
                        $stats[$key] = $v;
                    }
                }
            } else { // prometheus
                foreach (['listen' => 'stats_listen', 'path' => 'stats_path', 'namespace' => 'stats_namespace', 'subsystem' => 'stats_subsystem'] as $key => $field) {
                    $v = trim((string)$instanceNode->$field);
                    if ($v !== '') {
                        $stats[$key] = $v;
                    }
                }
            }
            // Metric toggles (both types). Emit only when enabled so a default
            // node stays terse; nebula defaults both to false.
            if ((string)$instanceNode->stats_message_metrics === '1') {
                $stats['message_metrics'] = true;
            }
            if ((string)$instanceNode->stats_lighthouse_metrics === '1') {
                $stats['lighthouse_metrics'] = true;
            }
            $cfg['stats'] = $stats;
        }

        $out = "# Generated by os-nebula. Do not edit by hand.\n" . $this->emitYaml($cfg, 0);

        // Append the free-form advanced YAML fragment, if set.
        // Validation is deferred to `nebula -test` (no PHP YAML parser available).
        $advanced = (string)$instanceNode->advanced;
        if ($advanced !== '') {
            $out .= "\n" . $advanced;
            // Ensure the output ends with a newline.
            if (substr($out, -1) !== "\n") {
                $out .= "\n";
            }
        }

        return $out;
    }

    /**
     * Insert $value into $cfg at the dotted $path (e.g. "listen.host"),
     * creating nested associative maps as needed.
     *
     * @param array $cfg target array, modified by reference
     * @param string $path dotted yaml path
     * @param mixed $value scalar value to set at the leaf
     */
    private function assignPath(array &$cfg, string $path, $value): void
    {
        $keys = explode('.', $path);
        $ref = &$cfg;
        $last = array_pop($keys);
        foreach ($keys as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref[$last] = $value;
        unset($ref);
    }

    /**
     * Recursively hand-emit YAML for $node at the given indent depth.
     *
     * - 2-space indent per level.
     * - Maps: "key:" then nested, or "key: <scalar>" for leaf scalars.
     * - Lists: "key:" then each item as "- " with the item's map inline-indented.
     * - Scalars: bool -> bare true/false; int -> bare number; string -> double
     *   quoted (so e.g. host: "::" is never mis-parsed as a bare YAML value).
     *
     * @param mixed $node array (map or list) at this level
     * @param int $depth indent depth
     * @return string emitted YAML (always ends with a newline for non-empty input)
     */
    private function emitYaml($node, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        $out = '';
        foreach ($node as $key => $value) {
            $k = $this->emitKey($key);
            if (is_array($value) && $this->isList($value)) {
                $out .= $indent . $k . ":\n";
                $dashIndent = str_repeat('  ', $depth + 1) . '- ';
                if (!empty($value) && !is_array($value[0])) {
                    // List of scalars (e.g. lighthouse.hosts, relay.relays).
                    foreach ($value as $item) {
                        $out .= $dashIndent . $this->emitScalar($item) . "\n";
                    }
                } else {
                    // List of maps (the firewall rule lists).
                    foreach ($value as $item) {
                        $itemLines = $this->emitYaml($item, $depth + 2);
                        // Replace the leading indent of the first line with "- ".
                        $rest = substr($itemLines, strlen(str_repeat('  ', $depth + 2)));
                        $out .= $dashIndent . $rest;
                    }
                }
            } elseif (is_array($value)) {
                // Nested map.
                $out .= $indent . $k . ":\n";
                $out .= $this->emitYaml($value, $depth + 1);
            } else {
                $out .= $indent . $k . ': ' . $this->emitScalar($value) . "\n";
            }
        }
        return $out;
    }

    /**
     * Format a map key for YAML. Bareword keys (the plugin's own section names
     * and IPv4 CIDR/host keys) are emitted plain so existing output is
     * unchanged; anything else — IPv6 CIDRs ("::1/128"), interface-name regexes
     * ("docker.*"), keys with colons or spaces — is double-quoted so it can't be
     * mis-parsed. The safe-plain set is deliberately conservative.
     *
     * @param string|int $key
     * @return string
     */
    private function emitKey($key): string
    {
        $k = (string)$key;
        if ($k !== '' && preg_match('#^[A-Za-z0-9_][A-Za-z0-9_./\-]*$#', $k)) {
            return $k;
        }
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $k);
        return '"' . $escaped . '"';
    }

    /**
     * Parse a "<CIDR> = true|false" allow-list textarea into a map<cidr,bool>.
     * When $withInterfaces, lines of the form "interface <regex> = true|false"
     * are collected under a nested `interfaces` map (nebula's local_allow_list
     * special key). Malformed lines (no '=', empty key, unrecognized bool) are
     * skipped.
     *
     * @param string $raw textarea contents
     * @param bool $withInterfaces honour the "interface <regex>" prefix form
     * @return array map<cidr,bool> (possibly with an 'interfaces' sub-map)
     */
    private function parseAllowList(string $raw, bool $withInterfaces): array
    {
        $map = [];
        foreach (explode("\n", $raw) as $line) {
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $left = trim(substr($line, 0, $eq));
            $bool = $this->parseYamlBool(substr($line, $eq + 1));
            if ($left === '' || $bool === null) {
                continue;
            }
            if ($withInterfaces && preg_match('/^interface\s+(.+)$/i', $left, $m)) {
                if (!isset($map['interfaces']) || !is_array($map['interfaces'])) {
                    $map['interfaces'] = [];
                }
                $map['interfaces'][trim($m[1])] = $bool;
                continue;
            }
            $map[$left] = $bool;
        }
        return $map;
    }

    /**
     * Parse a boolean token (true/false/yes/no/on/off/1/0, case-insensitive) to
     * a bool, or null when unrecognized.
     *
     * @param string $raw
     * @return bool|null
     */
    private function parseYamlBool(string $raw): ?bool
    {
        $v = strtolower(trim($raw));
        if (in_array($v, ['true', '1', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($v, ['false', '0', 'no', 'off'], true)) {
            return false;
        }
        return null;
    }

    /**
     * The 127.0.0.1 TCP port this instance's always-on sshd debug server binds.
     *
     * Each instance runs its own debug server, so the ports must be distinct.
     * The port is derived deterministically from the instance uuid (an md5-based
     * base in [SSHD_PORT_BASE, +SSHD_PORT_SPAN)), and collisions are resolved in
     * sorted-uuid order. Because the model holds every instance, both this
     * renderer and the setup.php querier compute the identical mapping with no
     * stored field and no port-discovery race.
     *
     * @param string $uuid the instance uuid
     * @return int the derived 127.0.0.1 port
     */
    public function sshdPortFor(string $uuid): int
    {
        $base = 22000;
        $span = 4000;
        $portFor = fn(string $u): int => $base + (int)(hexdec(substr(md5($u), 0, 6)) % $span);

        $uuids = [];
        foreach ($this->instances->instance->iterateItems() as $inst) {
            $u = (string)$inst->getAttribute('uuid');
            if ($u !== '') {
                $uuids[] = $u;
            }
        }
        sort($uuids);

        $used = [];
        $assigned = [];
        foreach ($uuids as $u) {
            $p = $portFor($u);
            while (isset($used[$p])) {
                $p = $base + ((($p - $base) + 1) % $span);
            }
            $used[$p] = true;
            $assigned[$u] = $p;
        }

        return $assigned[$uuid] ?? $portFor($uuid);
    }

    // -------------------------------------------------------------------------
    // Referential integrity + expiry purge (shared by the API controllers and
    // their tests; kept on the model so both exercise the same code).
    // -------------------------------------------------------------------------

    /**
     * Is the CA $uuid still in use, and by whom? A CA cannot be removed while it
     * signs a certificate (caref) or is trusted by an instance (trusted_cas).
     * Returns a human-readable label for the first referencing entity, or null
     * when the CA is unreferenced and safe to delete.
     *
     * @param string $uuid CA uuid
     * @return string|null referencing-entity label, or null if unreferenced
     */
    public function caReferencedBy(string $uuid): ?string
    {
        if ($uuid === '') {
            return null;
        }
        foreach ($this->pki->certificates->certificate->iterateItems() as $cert) {
            if ((string)$cert->caref === $uuid) {
                return sprintf('certificate "%s"', (string)$cert->descr);
            }
        }
        foreach ($this->instances->instance->iterateItems() as $inst) {
            $trusted = array_map('trim', explode(',', (string)$inst->trusted_cas));
            if (in_array($uuid, $trusted, true)) {
                return sprintf('instance "%s"', (string)$inst->description);
            }
        }
        return null;
    }

    /**
     * Is the certificate $uuid still referenced by an instance (certref)?
     * Returns a label for the first referencing instance, or null if unreferenced.
     *
     * @param string $uuid certificate uuid
     * @return string|null referencing-instance label, or null if unreferenced
     */
    public function certReferencedBy(string $uuid): ?string
    {
        if ($uuid === '') {
            return null;
        }
        foreach ($this->instances->instance->iterateItems() as $inst) {
            if ((string)$inst->certref === $uuid) {
                return sprintf('instance "%s"', (string)$inst->description);
            }
        }
        return null;
    }

    /**
     * Has an ISO/`notAfter`-style timestamp passed? Empty or unparseable values
     * are treated as NOT expired (we never purge what we cannot date).
     *
     * @param string $validTo notAfter timestamp (e.g. 2027-06-05T00:00:00Z)
     */
    private function validToExpired(string $validTo): bool
    {
        $validTo = trim($validTo);
        if ($validTo === '') {
            return false;
        }
        $ts = strtotime($validTo);
        return $ts !== false && $ts < time();
    }

    /**
     * Delete every expired CA that is not still referenced. Referenced-but-expired
     * CAs are kept and their descriptions returned so the caller can report them.
     * Mutates the model (does not save — the caller validates + serializes).
     *
     * @return array{removed:int, skippedNames:string[]}
     */
    public function purgeExpiredAuthorities(): array
    {
        $toDelete = [];
        $skippedNames = [];
        foreach ($this->pki->authorities->authority->iterateItems() as $ca) {
            if (!$this->validToExpired((string)$ca->valid_to)) {
                continue;
            }
            $uuid = $ca->getAttribute('uuid');
            if ($this->caReferencedBy($uuid) !== null) {
                $skippedNames[] = (string)$ca->descr;
                continue;
            }
            $toDelete[] = $uuid;
        }
        foreach ($toDelete as $uuid) {
            $this->pki->authorities->authority->del($uuid);
        }
        return [
            'removed'      => count($toDelete),
            'skippedNames' => array_values(array_unique($skippedNames)),
        ];
    }

    /**
     * Delete every expired certificate that is not still referenced by an
     * instance. Referenced-but-expired certs are kept and named. Mutates the
     * model (does not save).
     *
     * @return array{removed:int, skippedNames:string[]}
     */
    public function purgeExpiredCertificates(): array
    {
        $toDelete = [];
        $skippedNames = [];
        foreach ($this->pki->certificates->certificate->iterateItems() as $cert) {
            if (!$this->validToExpired((string)$cert->valid_to)) {
                continue;
            }
            $uuid = $cert->getAttribute('uuid');
            if ($this->certReferencedBy($uuid) !== null) {
                $skippedNames[] = (string)$cert->descr;
                continue;
            }
            $toDelete[] = $uuid;
        }
        foreach ($toDelete as $uuid) {
            $this->pki->certificates->certificate->del($uuid);
        }
        return [
            'removed'      => count($toDelete),
            'skippedNames' => array_values(array_unique($skippedNames)),
        ];
    }

    /**
     * Delete every blocklist entry whose Expiry date has passed (whole-day
     * compare; empty or unparseable = never). Blocklist entries are referenced by
     * nothing, so none are skipped. This is the only thing that removes an
     * expired block — the renderer keeps blocking until purged. Mutates the model
     * (does not save).
     *
     * @return array{removed:int, skippedNames:string[]}
     */
    public function purgeExpiredBlocklist(): array
    {
        $today = date('Y-m-d');
        $toDelete = [];
        foreach ($this->pki->blocklist->entry->iterateItems() as $entry) {
            $expiry = trim((string)$entry->expiry);
            if ($expiry === '') {
                continue;
            }
            $ts = strtotime($expiry);
            if ($ts !== false && date('Y-m-d', $ts) < $today) {
                $toDelete[] = $entry->getAttribute('uuid');
            }
        }
        foreach ($toDelete as $uuid) {
            $this->pki->blocklist->entry->del($uuid);
        }
        return ['removed' => count($toDelete), 'skippedNames' => []];
    }

    /**
     * Format a scalar for YAML: bool bare, int bare, string double-quoted with
     * backslash and double-quote escaped.
     *
     * @param mixed $value
     * @return string
     */
    private function emitScalar($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string)$value;
        }
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string)$value);
        return '"' . $escaped . '"';
    }

    /**
     * Is $arr a sequential (list) array rather than an associative map?
     *
     * @param array $arr
     * @return bool
     */
    private function isList(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
