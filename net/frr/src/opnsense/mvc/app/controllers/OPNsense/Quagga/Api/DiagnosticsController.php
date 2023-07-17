<?php

/**
 *    Copyright (C) 2023 Deciso B.V.
 *    Copyright (C) 2017 Frank Wall
 *    Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
 *
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
 *
 */

namespace OPNsense\Quagga\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * Class DiagnosticsController
 * @package OPNsense\Quagga
 */
class DiagnosticsController extends ApiControllerBase
{
    private $allifnames = [];

    public function initialize()
    {
        parent::initialize();
        foreach (Config::getInstance()->object()->interfaces->children() as $key => $node) {
            $this->allifnames[(string)$node->if] = !empty((string)$node->descr) ? (string)$node->descr : $key;
        }
    }

    public function getIfDesc($ifname)
    {
           return !empty($this->allifnames[$ifname]) ? $this->allifnames[$ifname] : '';
    }

    private function configdJson($daemon, $name)
    {
        $response = (new Backend())->configdpRun('quagga', ['diagnostics', $daemon . "_" . $name, 'json']);
        return json_decode($response ?? '', true) ?? [];
    }

    public function generalrunningconfigAction(): array
    {
        return ["response" => (new Backend())->configdpRun('quagga diagnostics general_running-config')];
    }

    public function searchGeneralroute4Action(): array
    {
        $records = [];
        foreach ($this->configdJson('general', 'route4') as $routes) {
            foreach ($routes as $route) {
                foreach ($route['nexthops'] as $nexthop) {
                    $nexthop = array_merge($route, $nexthop);
                    unset($nexthop['nexthops']);
                    $nexthop['via'] = !empty($nexthop['ip']) ? $nexthop['ip'] : 'Directly Attached';
                    $nexthop['interfaceDescr'] = $this->getIfDesc($nexthop['interfaceName'] ?? '');
                    $records[] = $nexthop;
                }
            }
        }
        return $this->searchRecordsetBase($records);
    }

    public function searchGeneralroute6Action(): array
    {
        $records = [];
        foreach ($this->configdJson('general', 'route6') as $routes) {
            foreach ($routes as $route) {
                foreach ($route['nexthops'] as $nexthop) {
                    $nexthop = array_merge($route, $nexthop);
                    unset($nexthop['nexthops']);
                    $nexthop['via'] = !empty($nexthop['ip']) ? $nexthop['ip'] : 'Directly Attached';
                    $nexthop['interfaceDescr'] = $this->getIfDesc($nexthop['interfaceName'] ?? '');
                    $records[] = $nexthop;
                }
            }
        }
        return $this->searchRecordsetBase($records);
    }

    public function searchBgproute4Action(): array
    {
        $records = [];
        $payload = $this->configdJson("bgp", "route4");
        $baserecord = [];
        foreach ($payload as $key => $value) {
            if (!is_array($value)) {
                $baserecord[$key] = $value;
            }
        }
        if (!empty($payload['routes'])) {
            foreach ($payload['routes'] as $routes) {
                foreach ($routes as $route) {
                    foreach ($route['nexthops'] as $nexthop) {
                        $nexthop = array_merge($route, $nexthop);
                        unset($nexthop['nexthops']);
                        $nexthop['internal'] = !empty($nexthop['pathFrom']) && $nexthop['pathFrom'] == 'internal';
                        $nexthop['path'] = !empty($nexthop['path']) ? $nexthop['path'] : 'Internal';
                        $records[] = array_merge($baserecord, $nexthop);
                    }
                }
            }
        }
        $result = $this->searchRecordsetBase($records);
        if (!empty($baserecord)) {
            $result['subtitle'] = sprintf(
                '%s : %s , %s : %s',
                gettext('routerId'),
                $baserecord['routerId'],
                gettext('localAS'),
                $baserecord['localAS']
            );
        }
        return $result;
    }

    public function searchBgproute6Action(): array
    {
        $records = [];
        $payload = $this->configdJson("bgp", "route6");
        $baserecord = [];
        foreach ($payload as $key => $value) {
            if (!is_array($value)) {
                $baserecord[$key] = $value;
            }
        }
        if (!empty($payload['routes'])) {
            foreach ($payload['routes'] as $routes) {
                foreach ($routes as $route) {
                    foreach ($route['nexthops'] as $nexthop) {
                        $nexthop = array_merge($route, $nexthop);
                        unset($nexthop['nexthops']);
                        $nexthop['internal'] = !empty($nexthop['pathFrom']) && $nexthop['pathFrom'] == 'internal';
                        $nexthop['path'] = !empty($nexthop['path']) ? $nexthop['path'] : 'Internal';
                        $records[] = array_merge($baserecord, $nexthop);
                    }
                }
            }
        }
        $result = $this->searchRecordsetBase($records);
        if (!empty($baserecord)) {
            $result['subtitle'] = sprintf(
                '%s : %s , %s : %s',
                gettext('routerId'),
                $baserecord['routerId'],
                gettext('localAS'),
                $baserecord['localAS']
            );
        }
        return $result;
    }

    public function bgpsummaryAction(): array
    {
        return ['response' => $this->configdJson("bgp", "summary")];
    }

    public function bgpneighborsAction(): array
    {
        return ['response' => $this->configdJson("bgp", "neighbors")];
    }

    public function ospfoverviewAction(): array
    {
        return ['response' => $this->configdJson("ospf", "overview")];
    }

    public function searchOspfneighborAction(): array
    {
        $records = [];
        $payload = $this->configdJson("ospf", "neighbor");
        if (!empty($payload['neighbors'])) {
            foreach ($payload['neighbors'] as $neighborid => $neighbor) {
                foreach ($neighbor as $item) {
                    $item['neighborid'] = $neighborid;
                    $records[] = $item;
                }
            }
        }
        return $this->searchRecordsetBase($records);
    }

    public function searchOspfrouteAction(): array
    {
        $records = [];
        $payload = $this->configdJson('ospf', 'route');
        foreach ($payload as $net => $network) {
            if (empty($network['nexthops'])) {
                continue;
            }
            foreach ($network['nexthops'] as $nexthop) {
                $records[] = [
                    'type' => $network['routeType'],
                    'network' => $net,
                    'cost' => $network['cost'],
                    'area' => $network['area'] ?? '',
                    'via' => !empty($nexthop['via']) ? $nexthop['ip'] : 'Directly Attached',
                    'viainterface' => !empty($nexthop['via']) ? $nexthop['via'] : $nexthop['directlyAttachedTo'],
                    'viainterfaceDescr' =>  $this->getIfDesc($nexthop['via'] ?? $nexthop['directlyAttachedTo']),
                ];
            }
        }

        return $this->searchRecordsetBase($records);
    }

    public function ospfdatabaseAction(): array
    {
        return ['response' => $this->configdJson("ospf", "database")];
    }

    public function ospfinterfaceAction(): array
    {
        return ['response' => $this->configdJson("ospf", "interface")];
    }

    public function ospfv3interfaceAction(): array
    {
        return ['response' => $this->configdJson("ospfv3", "interface")];
    }

    public function ospfv3overviewAction(): array
    {
        return ['response' => $this->configdJson("ospfv3", "overview")];
    }

    public function searchOspfv3routeAction($format = "json"): array
    {
        $records = [];
        $payload = $this->configdJson("ospfv3", "route");
        if (!empty($payload['routes'])) {
            foreach ($payload['routes'] as $net => $route) {
                if (!empty($route['nextHops'])) {
                    foreach ($route['nextHops'] as $nexthop) {
                        $record = array_merge($route, $nexthop);
                        $record['network'] = $net;
                        $record['interfaceDescr'] = $this->getIfDesc($record['interfaceName']);
                        $records[] = $record;
                    }
                }
            }
        }
        return $this->searchRecordsetBase($records);
    }

    public function searchOspfv3databaseAction(): array
    {
        $records = [];
        $payload = $this->configdJson("ospfv3", "database");
        foreach ($payload as $dbname => $database) {
            foreach ($database as $topic) {
                if (!empty($topic['lsa'])) {
                    foreach ($topic['lsa'] as $record) {
                        $record['dbname'] = $dbname;
                        $record['interface'] = $topic['interface'] ?? '';
                        $record['interfaceDescr'] = $this->getIfDesc($topic['interface'] ?? null);
                        $record['areaId'] = $topic['areaId'] ?? '';
                        $records[] = $record;
                    }
                }
            }
        }
        return $this->searchRecordsetBase($records);
    }

    private function bfdTreeFetch($topic)
    {
        $records = [];
        $payload = $this->configdJson("bfd", $topic);
        if (!empty($payload)) {
            foreach ($payload as $peer) {
                $peerid = $peer['peer'];
                $records[$peerid] = $peer;
            }
        }
        return  ["response" => $records];
    }

    public function bfdsummaryAction(): array
    {
        $records = [];
        $payload = (new Backend())->configdpRun('quagga diagnostics bfd_summary');
        foreach (explode("\n", $payload) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) == 4 && filter_var($parts[0], FILTER_VALIDATE_INT) !== false) {
                $records[] = [
                    'id' => $parts[0],
                    'local' => $parts[1],
                    'peer' => $parts[2],
                    'status' => $parts[3]
                ];
            }
        }
        return $this->searchRecordsetBase($records);
    }

    public function bfdneighborsAction(): array
    {
        return $this->bfdTreeFetch('neighbors');
    }

    public function bfdcountersAction(): array
    {
        return $this->bfdTreeFetch('counters');
    }
}
