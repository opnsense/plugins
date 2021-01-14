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
        $backend = new Backend();
        $response = $backend->configdRun("quagga diagnostics ".$daemon."_".$name.($format === "json" ? "_json" : ""));
        return array("response" => ($format === "json" ? json_decode($response) : $response));
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
            return array("response" => $routes4.$routes6);
        }
    }

    public function generalroute4Action($format = "json"): array
    {
        return $this->getInformation("general", "route4", $format);
    }

    public function generalroute6Action($format = "json"): array
    {
        return $this->getInformation("general", "route6", $format);
    }

    public function bgprouteAction($format = "json"): array
    {
        return $this->getInformation("bgp", "route", $format);
    }

    public function bgproute4Action($format = "json"): array
    {
        return $this->getInformation("bgp", "route4", $format);
    }

    public function bgproute6Action($format = "json"): array
    {
        return $this->getInformation("bgp", "route6", $format);
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

    public function ospfrouteAction($format = "json"): array
    {
        return $this->getInformation("ospf", "route", $format);
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
