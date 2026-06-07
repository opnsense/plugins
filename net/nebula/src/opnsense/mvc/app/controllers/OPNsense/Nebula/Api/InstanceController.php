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

class InstanceController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'nebula';
    protected static $internalModelClass = 'OPNsense\Nebula\Nebula';

    public function searchItemAction()
    {
        $result = $this->searchBase(
            'instances.instance',
            ['enabled', 'tun_name', 'description', 'listen_host', 'listen_port', 'am_lighthouse', 'certref']
        );
        // Enrich each visible row with the live daemon status so the grid's
        // Status column renders data-driven. (The Tabulator-based bootgrid
        // re-renders cells from the formatter, so an async per-row fill on the
        // 'loaded' event does not persist — the value must be in the row data.)
        if (!empty($result['rows']) && is_array($result['rows'])) {
            $backend = new Backend();

            // Build a certificate uuid -> description map so we can resolve each
            // instance's certref to a readable name here.  A certref that points
            // to a certificate which no longer exists (a dangling reference left
            // by a deleted cert) is surfaced as "(deleted)" rather than rendering
            // as a phantom name or an empty cell.
            $certByUuid = [];
            foreach ($this->getModel()->pki->certificates->certificate->iterateItems() as $cert) {
                $certByUuid[$cert->getAttribute('uuid')] = [
                    'descr' => (string)$cert->descr,
                    'fp'    => (string)$cert->fingerprint,
                ];
            }

            foreach ($result['rows'] as &$row) {
                $uuid = $row['uuid'] ?? '';
                $row['running'] = '0';
                $row['pid'] = '';
                if ($uuid !== '') {
                    $st = json_decode(trim($backend->configdpRun('nebula status_instance', [$uuid])), true);
                    if (is_array($st)) {
                        $row['running'] = !empty($st['running']) ? '1' : '0';
                        $row['pid'] = (isset($st['pid']) && $st['pid'] !== null) ? (string)$st['pid'] : '';
                    }
                }

                // Resolve certref -> certificate description for the grid.  The
                // searchBase 'certref' value is the raw stored uuid string.
                $certref = trim((string)($row['certref'] ?? ''));
                if ($certref === '') {
                    $row['certificate'] = '';
                } elseif (array_key_exists($certref, $certByUuid)) {
                    // "name: 0123abcd" (descr + first 8 hex of the fingerprint) so
                    // like-named host certs are distinguishable in the grid.
                    $c = $certByUuid[$certref];
                    $row['certificate'] = $c['fp'] !== ''
                        ? $c['descr'] . ': ' . substr($c['fp'], 0, 8)
                        : $c['descr'];
                } else {
                    $row['certificate'] = '(deleted)';
                }
            }
            unset($row);
        }
        return $result;
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase('instance', 'instances.instance', $uuid);
    }

    /**
     * Reject a posted instance whose listen_port or tun_name collides with an
     * existing instance on the same host.  Two daemons cannot share a UDP listen
     * port or a tun device name.
     *
     * The form posts the instance fields nested under the "instance" key (same
     * gotcha as the firewall matcher validation), so we read from there.
     *
     * @param string|null $editUuid uuid being edited (excluded from the scan), or null on add
     * @return array|null  null = no collision; array = ['result'=>'failed','validations'=>[...]]
     */
    private function checkUniqueListener(?string $editUuid): ?array
    {
        $instance = $this->request->getPost('instance');
        if (!is_array($instance)) {
            $instance = [];
        }

        $listenPort = isset($instance['listen_port']) ? trim((string)$instance['listen_port']) : '';
        $tunName    = isset($instance['tun_name']) ? trim((string)$instance['tun_name']) : '';

        // Port 0 = "random port"; it is allowed to repeat across instances.
        $checkPort = ($listenPort !== '' && (int)$listenPort !== 0);
        $checkTun  = ($tunName !== '');

        if (!$checkPort && !$checkTun) {
            return null;
        }

        foreach ($this->getModel()->instances->instance->iterateItems() as $node) {
            if ($node->getAttribute('uuid') === $editUuid) {
                continue; // exclude the row being edited
            }
            if ($checkPort && trim((string)$node->listen_port) === $listenPort) {
                return [
                    'result'      => 'failed',
                    'validations' => ['instance.listen_port' => 'port already used by another instance'],
                ];
            }
            if ($checkTun && trim((string)$node->tun_name) === $tunName) {
                return [
                    'result'      => 'failed',
                    'validations' => ['instance.tun_name' => 'interface name already used by another instance'],
                ];
            }
        }

        return null;
    }

    /**
     * Reject a posted instance whose stats telemetry block is missing the
     * destination required by its selected type. nebula needs stats.host for
     * graphite and stats.listen for prometheus; catching it here gives a field
     * error instead of a late `nebula -test` failure on Apply. Blank stats_type
     * (telemetry disabled) imposes no requirement.
     *
     * @return array|null  null = ok; array = failed-validation payload
     */
    private function checkStatsRequired(): ?array
    {
        $instance = $this->request->getPost('instance');
        if (!is_array($instance)) {
            return null;
        }
        $type = isset($instance['stats_type']) ? trim((string)$instance['stats_type']) : '';
        if ($type === 'graphite' && trim((string)($instance['stats_host'] ?? '')) === '') {
            return [
                'result'      => 'failed',
                'validations' => ['instance.stats_host' => 'host is required when stats type is graphite'],
            ];
        }
        if ($type === 'prometheus' && trim((string)($instance['stats_listen'] ?? '')) === '') {
            return [
                'result'      => 'failed',
                'validations' => ['instance.stats_listen' => 'listen is required when stats type is prometheus'],
            ];
        }
        return null;
    }

    public function addItemAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        $collision = $this->checkUniqueListener(null);
        if ($collision !== null) {
            return $collision;
        }
        $stats = $this->checkStatsRequired();
        if ($stats !== null) {
            return $stats;
        }
        return $this->addBase('instance', 'instances.instance');
    }

    public function setItemAction($uuid = null)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        $collision = $this->checkUniqueListener($uuid);
        if ($collision !== null) {
            return $collision;
        }
        $stats = $this->checkStatsRequired();
        if ($stats !== null) {
            return $stats;
        }
        return $this->setBase('instance', 'instances.instance', $uuid);
    }

    public function delItemAction($uuid = null)
    {
        return $this->delBase('instances.instance', $uuid);
    }

    public function toggleItemAction($uuid = null, $enabled = null)
    {
        return $this->toggleBase('instances.instance', $uuid, $enabled);
    }

    /**
     * Reload (restart) a single instance daemon by uuid.
     * Mirrors the per-row reload button on Interfaces : Overview.
     */
    public function reloadAction($uuid = null)
    {
        if ($this->request->isPost() && $uuid != null) {
            $backend = new Backend();
            $output = trim((string)$backend->configdpRun('nebula restart_instance', [$uuid]));
            // restart_instance (type:script_output) emits a JSON result line:
            //   {"started":true}  or  {"started":false,"error":"<reason>"}
            // Surface it to the UI so a failed (re)start shows the real reason
            // instead of silently doing nothing.
            $decoded = json_decode($output, true);
            if (is_array($decoded) && array_key_exists('started', $decoded)) {
                $started = !empty($decoded['started']);
                return [
                    'status'  => $started ? 'ok' : 'failed',
                    'started' => $started,
                    'error'   => $started ? '' : (string)($decoded['error'] ?? 'instance failed to start'),
                ];
            }
            // Fall back to the raw output if it wasn't the expected JSON shape.
            return ['status' => $output !== '' ? $output : 'ok', 'started' => true, 'error' => ''];
        }
        return ['status' => 'failed', 'started' => false, 'error' => 'bad request'];
    }

    /**
     * Report the running status of a single instance by uuid.
     * Returns ['running' => bool, 'pid' => int|null] (decoded from the
     * status_instance configd action's JSON output).
     */
    public function statusAction($uuid = null)
    {
        if ($uuid != null) {
            $backend = new Backend();
            $output = (string)$backend->configdpRun('nebula status_instance', [$uuid]);
            $decoded = json_decode(trim($output), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return ['running' => false, 'pid' => null];
    }

    /**
     * Full diagnostics for one instance (Status page): the runtime data from the
     * diag_instance configd action (process/tun/config-test), enriched with the
     * model-held config and the resolved host certificate summary.
     */
    public function diagAction($uuid = null)
    {
        if (empty($uuid)) {
            return ['found' => false];
        }
        $backend = new Backend();
        $diag = json_decode(trim((string)$backend->configdpRun('nebula diag_instance', [$uuid])), true);
        if (!is_array($diag)) {
            $diag = ['running' => false, 'pid' => null];
        }
        $diag['found'] = false;

        $mdl = $this->getModel();
        foreach ($mdl->instances->instance->iterateItems() as $inst) {
            if ($inst->getAttribute('uuid') !== $uuid) {
                continue;
            }
            $diag['found'] = true;
            $diag['description'] = (string)$inst->description;
            $diag['enabled'] = ((string)$inst->enabled === '1');
            $diag['am_lighthouse'] = ((string)$inst->am_lighthouse === '1');
            // The sshd debug server is always on; diagnostics are available
            // whenever the instance is running (no per-instance opt-in).
            $diag['diag_available'] = !empty($diag['running']);
            $diag['listen'] = (string)$inst->listen_host . ':' . (string)$inst->listen_port;

            // Resolve the host certificate summary (certref -> certificate).
            $certref = (string)$inst->certref;
            $diag['cert'] = null;
            if ($certref !== '') {
                foreach ($mdl->pki->certificates->certificate->iterateItems() as $crt) {
                    if ($crt->getAttribute('uuid') === $certref) {
                        $diag['cert'] = [
                            'name' => (string)$crt->cn,
                            'descr' => (string)$crt->descr,
                            'groups' => (string)$crt->groups,
                            'networks' => (string)$crt->networks,
                            'valid_to' => (string)$crt->valid_to,
                        ];
                        break;
                    }
                }
            }
            break;
        }
        return $diag;
    }

    /**
     * Live tunnels/peers for one instance, read from its always-on nebula sshd
     * debug server. Returns {ok:bool, peers?:..., error?:string}.
     */
    public function peersAction($uuid = null)
    {
        if (empty($uuid)) {
            return ['ok' => false, 'error' => 'missing uuid'];
        }
        $backend = new Backend();
        $decoded = json_decode(trim((string)$backend->configdpRun('nebula peers_instance', [$uuid])), true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'no response'];
        }
        return $decoded;
    }

    /**
     * One-shot Status-page snapshot for all instances (status + diagnostics +
     * cert + live peers/handshaking, one debug-server SSH per instance). Lets
     * the Status page poll with a single request instead of ~4 per instance.
     */
    public function snapshotAction()
    {
        $backend = new Backend();
        $decoded = json_decode(trim((string)$backend->configdpRun('nebula snapshot')), true);
        if (!is_array($decoded)) {
            return ['instances' => []];
        }
        return $decoded;
    }

    /**
     * Run a whitelisted debug-server sub-action against a running instance (the
     * Status page enrichment + verbs). POST so the mutating verbs (close,
     * change-remote, create-tunnel) are not triggerable by a bare GET.
     *
     * @param string|null $uuid   instance uuid
     * @param string|null $action sub-action (tunnel|cert|pending|deviceinfo|querylh|close|changeremote|createtunnel)
     * @return array {ok:bool, ...}
     */
    public function debugAction($uuid = null, $action = null)
    {
        if (!$this->request->isPost()) {
            return ['ok' => false, 'error' => 'POST required'];
        }
        if (empty($uuid) || empty($action)) {
            return ['ok' => false, 'error' => 'missing parameters'];
        }
        $vpn = trim((string)$this->request->getPost('vpn', 'string', ''));
        $remote = trim((string)$this->request->getPost('remote', 'string', ''));
        $backend = new Backend();
        $decoded = json_decode(
            trim((string)$backend->configdpRun('nebula debug_instance', [$uuid, $action, $vpn, $remote])),
            true
        );
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'no response'];
        }
        return $decoded;
    }
}
