<?php

/**
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
    private function getInformation(string $daemon, string $name, string $format): array
    {
        $response = (new Backend())->configdRun(
            "quagga diagnostics " . $daemon . "_" . $name . ($format === "json" ? "_json" : "")
        );
        return ["response" => ($format === "json" ? json_decode($response ?? '', true) : $response)];
    }

    public function generalrunningconfigAction(): array
    {
        return $this->getInformation("general", "running-config", "plain");
    }

    public function generalrouteAction($format = "json"): array
    {
        $routes4 = $this->getInformation("general", "route4", $format)['response'];
        $routes6 = $this->getInformation("general", "route6", $format)['response'];
        if ($format === "json") {
            return array("response" => array("ipv4" => $routes4, "ipv6" => $routes6));
        } else {
            return array("response" => $routes4 . $routes6);
        }
    }

    public function generalroute4Action($format = "json"): array
    {
        return $this->getInformation("general", "route4", $format);
    }

    public function searchGeneralroute4Action(): array
    {
        $records = [];
        foreach($this->getInformation("general", "route4", "json")['response'] as $route) {
            foreach ($route as $nexthop) {
                $nexthop['via'] = !empty($nexthop['ip']) ? $nexthop['ip'] : 'Directly Attached';
                $records[] = $nexthop;
            }
        }
        return $this->searchRecordsetBase($records);
    }

    public function generalroute6Action($format = "json"): array
    {
        return $this->getInformation("general", "route6", $format);
    }

    public function searchGeneralroute6Action(): array
    {
        $records = [];
        foreach($this->getInformation("general", "route6", "json")['response'] as $route) {
            foreach ($route as $nexthop) {
                $nexthop['via'] = !empty($nexthop['ip']) ? $nexthop['ip'] : 'Directly Attached';
                $records[] = $nexthop;
            }
        }
        return $this->searchRecordsetBase($records);
    }

    public function bgprouteAction($format = "json"): array
    {
        return $this->getInformation("bgp", "route", $format);
    }

    public function bgproute4Action($format = "json"): array
    {
        return $this->getInformation("bgp", "route4", $format);
    }

    public function searchBgproute4Action(): array
    {
        $records = [];
        $payload = $this->getInformation("bgp", "route4", "json")['response'];
        $baserecord = [];
        foreach ($payload as $key => $value) {
            if (!is_array($value)) {
                $baserecord[$key] = $value;
            }
        }
        if (!empty($payload['routes'])) {
            foreach ($payload['routes'] as $route) {
                foreach ($route as $nexthop) {
                    $nexthop['internal'] = !empty($nexthop['pathFrom']) && $nexthop['pathFrom'] == 'internal';
                    $nexthop['path'] = !empty($nexthop['path']) ? $nexthop['path'] : 'Internal';
                    $records[] = array_merge($baserecord, $nexthop);
                }
            }
        }
        return $this->searchRecordsetBase($records);
    }

    public function bgproute6Action($format = "json"): array
    {
        return $this->getInformation("bgp", "route6", $format);
    }

    public function searchBgproute6Action(): array
    {
        $records = [];
        $payload = $this->getInformation("bgp", "route6", "json")['response'];
        $baserecord = [];
        foreach ($payload as $key => $value) {
            if (!is_array($value)) {
                $baserecord[$key] = $value;
            }
        }
        if (!empty($payload['routes'])) {
            foreach ($payload['routes'] as $route) {
                foreach ($route as $nexthop) {
                    $nexthop['internal'] = !empty($nexthop['pathFrom']) && $nexthop['pathFrom'] == 'internal';
                    $nexthop['path'] = !empty($nexthop['path']) ? $nexthop['path'] : 'Internal';
                    $records[] = array_merge($baserecord, $nexthop);
                }
            }
        }
        return $this->searchRecordsetBase($records);
    }

    public function bgpsummaryAction($format = "json"): array
    {
        return $this->getInformation("bgp", "summary", $format);
    }

    public function bgpneighborsAction($format = "json"): array
    {
        return $this->getInformation("bgp", "neighbors", $format);
    }

    public function ospfoverviewAction($format = "json"): array
    {
        return $this->getInformation("ospf", "overview", $format);
    }

    public function ospfneighborAction($format = "json"): array
    {
        return $this->getInformation("ospf", "neighbor", $format);
    }

    public function searchOspfneighborAction(): array
    {
        $records = [];
        $payload = $this->getInformation("ospf", "neighbor", "json")['response'];
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

    public function ospfrouteAction($format = "json"): array
    {
        return $this->getInformation("ospf", "route", $format);
    }

    public function searchOspfrouteAction(): array
    {
        $records = [];
        $payload = $this->getInformation("ospf", "route", "json")['response'];
        foreach($payload as $net => $network) {
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
                    'viainterface' => !empty($nexthop['via']) ? $nexthop['via'] : $nexthop['directly attached to'],
                ];
            }
        }

        return $this->searchRecordsetBase($records);
    }

    public function ospfdatabaseAction($format = "json"): array
    {
        return $this->getInformation("ospf", "database", $format);
    }

    public function ospfinterfaceAction($format = "json"): array
    {
        return $this->getInformation("ospf", "interface", $format);
    }

    public function ospfv3overviewAction($format = "json"): array
    {
        return $this->getInformation("ospfv3", "overview", $format);
    }

    public function ospfv3neighborAction($format = "json"): array
    {
        return $this->getInformation("ospfv3", "neighbor", $format);
    }

    public function ospfv3routeAction($format = "json"): array
    {
        return $this->getInformation("ospfv3", "route", $format);
    }

    public function ospfv3databaseAction($format = "json"): array
    {
        return $this->getInformation("ospfv3", "database", $format);
    }

    public function ospfv3interfaceAction($format = "json"): array
    {
        return $this->getInformation("ospfv3", "interface", $format);
    }
}
