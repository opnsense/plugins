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

        return $data;
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
            $response = $backend->configdRun('hclouddns updatev2');
            $data = json_decode($response, true);

            if ($data === null) {
                return ['status' => 'error', 'message' => 'Update failed', 'raw' => $response];
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
     * Test notification channels
     * @return array
     */
    public function testNotifyAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('hclouddns testnotify');
            $data = json_decode(trim($response), true);

            if ($data !== null) {
                return $data;
            }
            return ['status' => 'error', 'message' => 'Test notification failed'];
        }

        return ['status' => 'error', 'message' => 'POST request required'];
    }
}
