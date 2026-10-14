<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2026 Michael Pihlblad

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\CrowdSec\General;

/**
 * Status of the enabled CrowdSec components, for the overview page
 * @package OPNsense\CrowdSec
 */
class StatusController extends ApiControllerBase
{
    /**
     * @param Backend $backend
     * @param string $action configd action printing the rc status of a service
     * @return array running state and pid
     */
    private function serviceStatus(Backend $backend, string $action): array
    {
        $output = $backend->configdRun("crowdsec {$action}");
        $running = preg_match('/is running as pid (\d+)/', $output, $matches) === 1;

        return [
            'running' => $running,
            'pid' => $running ? (int)$matches[1] : null,
        ];
    }

    /**
     * @param Backend $backend
     * @param string $action configd action printing json
     * @return mixed decoded json, null on error
     */
    private function configdJson(Backend $backend, string $action)
    {
        return json_decode(trim($backend->configdRun("crowdsec {$action}")), true);
    }

    /**
     * Retrieve the status of the enabled components
     *
     * @return array
     */
    public function getAction(): array
    {
        $mdl = new General();
        $backend = new Backend();

        $agent_enabled = $mdl->agent_enabled->isEqual('1');
        $lapi_enabled = $mdl->lapi_enabled->isEqual('1');
        $bouncer_enabled = $mdl->firewall_bouncer_enabled->isEqual('1');

        $result = [
            'agent_enabled' => $agent_enabled,
            'lapi_enabled' => $lapi_enabled,
            'bouncer_enabled' => $bouncer_enabled,
            'bouncer_remote_lapi' => $mdl->bouncerUsesRemoteLapi(),
            'manual_configuration' => $mdl->lapi_manual_configuration->isEqual('1'),
        ];

        // the log processor (agent) and the LAPI run in the same process
        if ($agent_enabled || $lapi_enabled) {
            $result['crowdsec'] = $this->serviceStatus($backend, 'crowdsec-status');
        }

        if ($lapi_enabled) {
            $machines = $this->configdJson($backend, 'machines-list');
            $bouncers = $this->configdJson($backend, 'bouncers-list');
            $result['lapi'] = [
                'machines' => is_array($machines) ? count($machines) : null,
                'bouncers' => is_array($bouncers) ? count($bouncers) : null,
            ];
        }

        if ($bouncer_enabled) {
            $info = $this->configdJson($backend, 'bouncer-status');
            $result['bouncer'] = array_merge(
                $this->serviceStatus($backend, 'crowdsec-firewall-status'),
                is_array($info) ? $info : []
            );
        }

        return $result;
    }

    /**
     * Whether a remote LAPI API key is stored, without revealing it
     *
     * @return array
     */
    public function keyAction(): array
    {
        return ['set' => !(new General())->remote_bouncer_api_key->isEmpty()];
    }

    /**
     * Check that the bouncer can reach its LAPI with its API key
     *
     * @return array result (ok, invalid_key, unreachable, error) and message
     */
    public function testAction(): array
    {
        if (!$this->request->isPost()) {
            $this->response->setStatusCode(405, "Method Not Allowed");
            $this->response->setHeader("Allow", "POST");
            return [];
        }

        $result = $this->configdJson(new Backend(), 'bouncer-test');
        if (!is_array($result)) {
            return ['result' => 'error', 'message' => gettext('The connection test did not return a result.')];
        }

        return $result;
    }
}
