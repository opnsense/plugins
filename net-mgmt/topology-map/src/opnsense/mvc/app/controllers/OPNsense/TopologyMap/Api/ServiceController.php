<?php

namespace OPNsense\TopologyMap\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\TopologyMap\TopologyMap;

class ServiceController extends ApiControllerBase
{
    private function isPublicIp($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function getSettings()
    {
        $model = new TopologyMap();
        return $model->general;
    }

    private function parseArp($output)
    {
        $items = [];
        foreach (preg_split('/\r?\n/', (string)$output) as $line) {
            if (preg_match('/\(([^\)]+)\)\s+at\s+([0-9a-f:]+)\s+on\s+(\S+)/i', $line, $m)) {
                $items[] = [
                    'ip' => $m[1],
                    'mac' => strtolower($m[2]),
                    'if' => $m[3],
                    'source' => 'arp'
                ];
            }
        }
        return $items;
    }

    private function parseNdp($output)
    {
        $items = [];
        foreach (preg_split('/\r?\n/', (string)$output) as $line) {
            if (preg_match('/^([0-9a-f:]+)\s+([0-9a-f:]+)\s+\S+\s+\S+\s+(\S+)$/i', trim($line), $m)) {
                $items[] = [
                    'ip' => strtolower($m[1]),
                    'mac' => strtolower($m[2]),
                    'if' => $m[3],
                    'source' => 'ndp'
                ];
            }
        }
        return $items;
    }

    private function parseLldp($output)
    {
        $items = [];
        $current = null;

        foreach (preg_split('/\r?\n/', (string)$output) as $line) {
            if (preg_match('/^Interface:\s*([^,]+),/i', $line, $m)) {
                if ($current !== null) {
                    $items[] = $current;
                }
                $current = [
                    'if' => trim($m[1]),
                    'sysname' => 'unknown',
                    'port' => 'unknown',
                    'source' => 'lldp'
                ];
                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^\s*SysName:\s*(.+)$/i', $line, $m)) {
                $current['sysname'] = trim($m[1]);
            } elseif (preg_match('/^\s*PortID:\s*(.+)$/i', $line, $m)) {
                $current['port'] = trim($m[1]);
            }
        }

        if ($current !== null) {
            $items[] = $current;
        }

        return $items;
    }

    private function buildTopology($interfaces, $arpItems, $ndpItems, $lldpItems, $maxNodes)
    {
        $nodes = [];
        $links = [];

        foreach ($interfaces as $if) {
            $if = trim($if);
            if ($if === '') {
                continue;
            }
            $nodes['if:' . $if] = [
                'id' => 'if:' . $if,
                'label' => $if,
                'type' => 'interface'
            ];
        }

        $neighborItems = array_merge($arpItems, $ndpItems);
        foreach ($neighborItems as $item) {
            $ifId = 'if:' . $item['if'];
            $hostId = 'host:' . $item['ip'];
            if (!isset($nodes[$hostId])) {
                $nodes[$hostId] = [
                    'id' => $hostId,
                    'label' => $item['ip'],
                    'ip' => $item['ip'],
                    'mac' => $item['mac'],
                    'type' => 'host',
                    'source' => $item['source']
                ];
            }
            $links[] = [
                'from' => $ifId,
                'to' => $hostId,
                'type' => $item['source']
            ];
        }

        foreach ($lldpItems as $item) {
            $ifId = 'if:' . $item['if'];
            $devId = 'lldp:' . $item['if'] . ':' . md5($item['sysname'] . ':' . $item['port']);
            if (!isset($nodes[$devId])) {
                $nodes[$devId] = [
                    'id' => $devId,
                    'label' => $item['sysname'],
                    'port' => $item['port'],
                    'type' => 'lldp-neighbor',
                    'source' => 'lldp'
                ];
            }
            $links[] = [
                'from' => $ifId,
                'to' => $devId,
                'type' => 'lldp'
            ];
        }

        $nodes = array_values($nodes);
        if (count($nodes) > $maxNodes) {
            $nodes = array_slice($nodes, 0, $maxNodes);
        }

        // Keep only links that still point to nodes after node capping.
        $allowedNodeIds = [];
        foreach ($nodes as $node) {
            $allowedNodeIds[$node['id']] = true;
        }

        $links = array_values(array_filter($links, function ($link) use ($allowedNodeIds) {
            return isset($allowedNodeIds[$link['from']]) && isset($allowedNodeIds[$link['to']]);
        }));

        return ['nodes' => $nodes, 'links' => $links];
    }

    private function collectDiscoveryData()
    {
        $settings = $this->getSettings();
        if ((string)$settings->enabled === '0') {
            return ['status' => 'failed', 'message' => 'Topology mapper is disabled'];
        }

        $backend = new Backend();
        $interfacesRaw = trim($backend->configdRun('topologymap interfaces'));
        $interfaces = preg_split('/\s+/', $interfacesRaw);

        $arpItems = [];
        $ndpItems = [];
        $lldpItems = [];

        if ((string)$settings->useArp === '1') {
            $arpItems = $this->parseArp($backend->configdRun('topologymap arp'));
        }

        if ((string)$settings->useNdp === '1') {
            $ndpItems = $this->parseNdp($backend->configdRun('topologymap ndp'));
        }

        if ((string)$settings->useLldp === '1') {
            $lldpItems = $this->parseLldp($backend->configdRun('topologymap lldp'));
        }

        $maxNodes = (int)$settings->maxNodes;
        if ($maxNodes < 1) {
            $maxNodes = 500;
        }

        $topology = $this->buildTopology($interfaces, $arpItems, $ndpItems, $lldpItems, $maxNodes);

        return [
            'status' => 'ok',
            'settings' => $settings,
            'summary' => [
                'interfaces' => count(array_filter($interfaces)),
                'hosts' => count($arpItems) + count($ndpItems),
                'neighbors' => count($lldpItems),
                'nodes' => count($topology['nodes']),
                'links' => count($topology['links'])
            ],
            'topology' => $topology
        ];
    }

    private function buildGeoDataset($topology)
    {
        $points = [];

        foreach (($topology['nodes'] ?? []) as $node) {
            if (($node['type'] ?? '') !== 'host') {
                continue;
            }

            $ip = (string)($node['ip'] ?? '');
            if (!$this->isPublicIp($ip)) {
                continue;
            }

            // Coordinates are intentionally null when no local geolocation provider is configured.
            $points[] = [
                'id' => $node['id'],
                'label' => $node['label'] ?? $ip,
                'ip' => $ip,
                'lat' => null,
                'lon' => null,
                'provider' => 'none'
            ];
        }

        return $points;
    }

    public function discoverAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'message' => 'POST required'];
        }

        $resp = $this->collectDiscoveryData();
        if (($resp['status'] ?? 'failed') !== 'ok') {
            return $resp;
        }

        $settings = $resp['settings'];
        $topology = $resp['topology'];
        $geoPoints = ((string)$settings->showGeoMap === '1') ? $this->buildGeoDataset($topology) : [];

        return [
            'status' => 'ok',
            'summary' => $resp['summary'],
            'topology' => $topology,
            'geomap' => $geoPoints,
            'meta' => [
                'geo_enabled' => ((string)$settings->showGeoMap === '1') ? 'yes' : 'no',
                'geo_points' => count($geoPoints)
            ]
        ];
    }

    public function summaryAction()
    {
        $resp = $this->collectDiscoveryData();
        if (!is_array($resp) || ($resp['status'] ?? 'failed') !== 'ok') {
            return ['status' => 'failed'];
        }

        return ['status' => 'ok', 'summary' => $resp['summary']];
    }

    public function geomapAction()
    {
        $resp = $this->collectDiscoveryData();
        if (!is_array($resp) || ($resp['status'] ?? 'failed') !== 'ok') {
            return ['status' => 'failed'];
        }

        if ((string)$resp['settings']->showGeoMap !== '1') {
            return [
                'status' => 'ok',
                'enabled' => 'no',
                'points' => []
            ];
        }

        return [
            'status' => 'ok',
            'enabled' => 'yes',
            'points' => $this->buildGeoDataset($resp['topology'])
        ];
    }
}
