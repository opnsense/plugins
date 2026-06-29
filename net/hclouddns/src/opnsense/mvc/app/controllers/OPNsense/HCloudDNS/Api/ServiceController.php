<?php

/**
 *    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\HCloudDNS\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\HCloudDNS\HCloudDNS;

/**
 * Class ServiceController
 * @package OPNsense\HCloudDNS\Api
 */
class ServiceController extends ApiControllerBase
{
    /**
     * Get service status
     * @return array
     */
    public function statusAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('hclouddns status');
        $data = json_decode($response, true);

        if ($data === null) {
            return ['status' => 'error', 'message' => 'Failed to get status'];
        }

        // Determine running/stopped for updateServiceControlUI
        $mdl = new HCloudDNS();
        $enabled = (string)$mdl->general->enabled === '1';
        $stopped = file_exists('/var/run/hclouddns.stopped');
        $data['status'] = ($enabled && !$stopped) ? 'running' : 'stopped';

        return $data;
    }

    /**
     * Start service
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            @unlink('/var/run/hclouddns.stopped');
            $backend = new Backend();
            $response = $backend->configdRun('hclouddns start');
            return ['status' => 'ok'];
        }
        return ['status' => 'error', 'message' => 'POST request required'];
    }

    /**
     * Stop service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('hclouddns stop');
            return ['status' => 'ok'];
        }
        return ['status' => 'error', 'message' => 'POST request required'];
    }

    /**
     * Restart service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            @unlink('/var/run/hclouddns.stopped');
            $backend = new Backend();
            $response = $backend->configdRun('hclouddns update');
            return ['status' => 'ok'];
        }
        return ['status' => 'error', 'message' => 'POST request required'];
    }

    /**
     * Get list of CARP VIPs from system config
     * @return array
     */
    public function getVipListAction()
    {
        $result = ['status' => 'ok', 'rows' => []];

        $config = \OPNsense\Core\Config::getInstance()->object();

        if (isset($config->virtualip) && isset($config->virtualip->vip)) {
            foreach ($config->virtualip->vip as $vip) {
                if ((string)$vip->mode === 'carp') {
                    $result['rows'][] = [
                        'vhid' => (string)$vip->vhid,
                        'subnet' => (string)$vip->subnet,
                        'interface' => (string)$vip->interface,
                        'descr' => (string)$vip->descr
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Trigger manual update
     * @return array
     */
    public function updateAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('hclouddns update');
            $data = json_decode($response, true);

            if ($data === null) {
                return ['status' => 'error', 'message' => 'Update failed'];
            }

            return $data;
        }

        return ['status' => 'error', 'message' => 'POST request required'];
    }

    /**
     * Reconfigure service (apply settings)
     * @return array
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $mdl = new HCloudDNS();
            $backend = new Backend();

            // Generate configuration if needed
            $backend->configdRun('template reload OPNsense/HCloudDNS');

            return ['status' => 'ok'];
        }

        return ['status' => 'error', 'message' => 'POST request required'];
    }

    /**
     * Trigger manual update with v2 failover support
     * @return array
     */
    public function updateV2Action()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('hclouddns update');
            $data = json_decode($response, true);

            if ($data === null) {
                return ['status' => 'error', 'message' => 'Update failed', 'raw' => $response];
            }

            return $data;
        }

        return ['status' => 'error', 'message' => 'POST request required'];
    }

    /**
     * Preview DNS changes (dry run)
     * @return array
     */
    public function previewAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('hclouddns dryrun');
            $data = json_decode($response, true);

            if ($data === null) {
                return ['status' => 'error', 'message' => 'Preview failed'];
            }

            return $data;
        }

        return ['status' => 'error', 'message' => 'POST request required'];
    }

    /**
     * Get failover history
     * @return array
     */
    public function failoverHistoryAction()
    {
        $stateFile = '/var/run/hclouddns_state.json';

        if (file_exists($stateFile)) {
            $content = file_get_contents($stateFile);
            $data = json_decode($content, true);

            if ($data !== null && isset($data['failoverHistory'])) {
                return [
                    'status' => 'ok',
                    'history' => $data['failoverHistory'],
                    'lastUpdate' => $data['lastUpdate'] ?? 0
                ];
            }
        }

        return ['status' => 'ok', 'history' => [], 'lastUpdate' => 0];
    }

    /**
     * Simulate gateway failure
     * @param string $uuid gateway UUID
     * @return array
     */
    public function simulateDownAction($uuid = null)
    {
        if ($this->request->isPost() && $uuid !== null) {
            $backend = new Backend();
            $response = $backend->configdpRun('hclouddns simulate down', [$uuid]);
            $data = json_decode(trim($response), true);

            if ($data !== null) {
                return $data;
            }
            return ['status' => 'error', 'message' => 'Simulation failed'];
        }

        return ['status' => 'error', 'message' => 'POST request with gateway UUID required'];
    }

    /**
     * Simulate gateway recovery
     * @param string $uuid gateway UUID
     * @return array
     */
    public function simulateUpAction($uuid = null)
    {
        if ($this->request->isPost() && $uuid !== null) {
            $backend = new Backend();
            $response = $backend->configdpRun('hclouddns simulate up', [$uuid]);
            $data = json_decode(trim($response), true);

            if ($data !== null) {
                return $data;
            }
            return ['status' => 'error', 'message' => 'Simulation failed'];
        }

        return ['status' => 'error', 'message' => 'POST request with gateway UUID required'];
    }

    /**
     * Clear all simulations
     * @return array
     */
    public function simulateClearAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('hclouddns simulate clear');
            $data = json_decode(trim($response), true);

            if ($data !== null) {
                return $data;
            }
            return ['status' => 'error', 'message' => 'Clear failed'];
        }

        return ['status' => 'error', 'message' => 'POST request required'];
    }

    /**
     * Get simulation status
     * @return array
     */
    public function simulateStatusAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('hclouddns simulate status');
        $data = json_decode(trim($response), true);

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'ok', 'simulation' => ['active' => false, 'simulatedDown' => []]];
    }

    /**
     * Start maintenance mode for a gateway
     * @param string $uuid gateway UUID
     * @return array
     */
    public function maintenanceStartAction($uuid = null)
    {
        if ($this->request->isPost() && $uuid !== null) {
            $mdl = new HCloudDNS();
            $node = $mdl->getNodeByReference('gateways.gateway.' . $uuid);

            if ($node === null) {
                return ['status' => 'error', 'message' => 'Gateway not found'];
            }

            $backend = new Backend();
            $response = $backend->configdpRun('hclouddns maintenance start', [$uuid]);
            $data = json_decode(trim($response), true);

            // Return immediately - DNS update is triggered by the frontend separately
            return $data ?? ['status' => 'error', 'message' => 'Failed to start maintenance'];
        }

        return ['status' => 'error', 'message' => 'POST request with gateway UUID required'];
    }

    /**
     * Stop maintenance mode for a gateway
     * @param string $uuid gateway UUID
     * @return array
     */
    public function maintenanceStopAction($uuid = null)
    {
        if ($this->request->isPost() && $uuid !== null) {
            $mdl = new HCloudDNS();
            $node = $mdl->getNodeByReference('gateways.gateway.' . $uuid);

            if ($node === null) {
                return ['status' => 'error', 'message' => 'Gateway not found'];
            }

            $backend = new Backend();
            $response = $backend->configdpRun('hclouddns maintenance stop', [$uuid]);
            $data = json_decode(trim($response), true);

            // Return immediately - DNS update is triggered by the frontend separately
            return $data ?? ['status' => 'error', 'message' => 'Failed to stop maintenance'];
        }

        return ['status' => 'error', 'message' => 'POST request with gateway UUID required'];
    }

    /**
     * Schedule maintenance window for a gateway
     * @param string $uuid gateway UUID
     * @return array
     */
    public function maintenanceScheduleAction($uuid = null)
    {
        if ($this->request->isPost() && $uuid !== null) {
            $mdl = new HCloudDNS();
            $node = $mdl->getNodeByReference('gateways.gateway.' . $uuid);

            if ($node === null) {
                return ['status' => 'error', 'message' => 'Gateway not found'];
            }

            $start = $this->request->getPost('start', 'string', '');
            $end = $this->request->getPost('end', 'string', '');

            if (empty($start) || empty($end)) {
                return ['status' => 'error', 'message' => 'Start and end datetime required'];
            }

            $backend = new Backend();
            $response = $backend->configdpRun('hclouddns maintenance schedule', [$uuid, $start, $end]);
            $data = json_decode(trim($response), true);

            if ($data !== null) {
                return $data;
            }
            return ['status' => 'error', 'message' => 'Failed to schedule maintenance'];
        }

        return ['status' => 'error', 'message' => 'POST request with gateway UUID required'];
    }

    /**
     * Check DNS propagation for a specific entry
     * @param string $uuid entry UUID
     * @return array propagation check result
     */
    public function propagationCheckAction($uuid = null)
    {
        if ($uuid === null) {
            return ['status' => 'error', 'message' => 'Entry UUID required'];
        }

        $mdl = new HCloudDNS();
        $node = $mdl->getNodeByReference('entries.entry.' . $uuid);

        if ($node === null) {
            return ['status' => 'error', 'message' => 'Entry not found'];
        }

        $recordName = (string)$node->recordName;
        $zoneName = (string)$node->zoneName;
        $recordType = (string)$node->recordType;

        // Get current IP from runtime state
        $stateFile = '/var/run/hclouddns_state.json';
        $currentIp = '';
        if (file_exists($stateFile)) {
            $state = json_decode(file_get_contents($stateFile), true) ?? [];
            $currentIp = $state['entries'][$uuid]['hetznerIp'] ?? '';
        }

        if (empty($currentIp)) {
            $currentIp = (string)$node->currentIp;
        }

        if (empty($currentIp)) {
            return ['status' => 'error', 'message' => 'No current IP known for this entry'];
        }

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns propagation check', [
            $recordName, $zoneName, $recordType, $currentIp
        ]);
        $data = json_decode(trim($response), true);

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'error', 'message' => 'Propagation check failed'];
    }

    /**
     * Test notification channels
     * @param string $channel Optional: email, webhook, ntfy (empty = all)
     * @return array
     */
    public function testNotifyAction($channel = '')
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $validChannels = ['email', 'webhook', 'ntfy', ''];
            if (!in_array($channel, $validChannels)) {
                return ['status' => 'error', 'message' => 'Invalid channel'];
            }
            $response = $backend->configdpRun('hclouddns testnotify', [$channel]);
            $data = json_decode(trim($response), true);

            if ($data !== null) {
                return $data;
            }
            return ['status' => 'error', 'message' => 'Test notification failed'];
        }

        return ['status' => 'error', 'message' => 'POST request required'];
    }
}
