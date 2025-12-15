<?php

/**
 * Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
 * All rights reserved.
 */

namespace OPNsense\HCloudDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class GatewaysController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\HCloudDNS\HCloudDNS';
    protected static $internalModelName = 'hclouddns';

    /**
     * Search gateways
     * @return array search results
     */
    public function searchItemAction()
    {
        return $this->searchBase('gateways.gateway', ['enabled', 'name', 'interface', 'priority', 'checkipMethod']);
    }

    /**
     * Get gateway by UUID
     * @param string $uuid item unique id
     * @return array gateway data
     */
    public function getItemAction($uuid = null)
    {
        return $this->getBase('gateway', 'gateways.gateway', $uuid);
    }

    /**
     * Add new gateway
     * @return array save result
     */
    public function addItemAction()
    {
        return $this->addBase('gateway', 'gateways.gateway');
    }

    /**
     * Update gateway
     * @param string $uuid item unique id
     * @return array save result
     */
    public function setItemAction($uuid)
    {
        return $this->setBase('gateway', 'gateways.gateway', $uuid);
    }

    /**
     * Delete gateway
     * @param string $uuid item unique id
     * @return array delete result
     */
    public function delItemAction($uuid)
    {
        return $this->delBase('gateways.gateway', $uuid);
    }

    /**
     * Toggle gateway enabled status
     * @param string $uuid item unique id
     * @param string $enabled desired state (0/1), leave empty to toggle
     * @return array result
     */
    public function toggleItemAction($uuid, $enabled = null)
    {
        return $this->toggleBase('gateways.gateway', $uuid, $enabled);
    }

    /**
     * Check health of a specific gateway
     * @param string $uuid gateway UUID
     * @return array health check result
     */
    public function checkHealthAction($uuid = null)
    {
        $result = ['status' => 'error', 'message' => 'Invalid gateway'];

        if ($uuid !== null) {
            $mdl = $this->getModel();
            $node = $mdl->getNodeByReference('gateways.gateway.' . $uuid);
            if ($node !== null) {
                $backend = new Backend();
                $response = $backend->configdpRun('hclouddns healthcheck', [$uuid]);
                $data = json_decode(trim($response), true);
                if ($data !== null) {
                    $result = $data;
                } else {
                    $result = ['status' => 'error', 'message' => 'Backend error', 'raw' => $response];
                }
            }
        }

        return $result;
    }

    /**
     * Get current IP for a gateway
     * @param string $uuid gateway UUID
     * @return array IP information
     */
    public function getIpAction($uuid = null)
    {
        $result = ['status' => 'error', 'message' => 'Invalid gateway'];

        if ($uuid !== null) {
            $mdl = $this->getModel();
            $node = $mdl->getNodeByReference('gateways.gateway.' . $uuid);
            if ($node !== null) {
                $backend = new Backend();
                $response = $backend->configdpRun('hclouddns getip', [$uuid]);
                $data = json_decode(trim($response), true);
                if ($data !== null) {
                    $result = $data;
                } else {
                    $result = ['status' => 'error', 'message' => 'Backend error', 'raw' => $response];
                }
            }
        }

        return $result;
    }

    /**
     * Get status of all gateways
     * @return array status information
     */
    public function statusAction()
    {
        $result = [
            'status' => 'ok',
            'gateways' => []
        ];

        // Load runtime state for simulation status
        $stateFile = '/var/run/hclouddns_state.json';
        $state = [];
        if (file_exists($stateFile)) {
            $content = file_get_contents($stateFile);
            $state = json_decode($content, true) ?? [];
        }

        // Get model data
        $mdl = $this->getModel();
        $gateways = $mdl->gateways->gateway;

        foreach ($gateways->iterateItems() as $uuid => $gw) {
            $gwState = $state['gateways'][$uuid] ?? [];

            $result['gateways'][$uuid] = [
                'uuid' => $uuid,
                'name' => (string)$gw->name,
                'interface' => (string)$gw->interface,
                'enabled' => (string)$gw->enabled,
                'status' => $gwState['status'] ?? 'unknown',
                'ipv4' => $gwState['ipv4'] ?? null,
                'ipv6' => $gwState['ipv6'] ?? null,
                'simulated' => $gwState['simulated'] ?? false,
                'lastCheck' => $gwState['lastCheck'] ?? 0
            ];
        }

        $result['lastUpdate'] = $state['lastUpdate'] ?? 0;

        return $result;
    }
}
