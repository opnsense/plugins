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

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Core\Config;

/**
 * Class DiagnosticsController
 * @package OPNsense\Quagga
 */
class DiagnosticsController extends ApiControllerBase
{
    /**
     * show ip bgp
     * @return array
     */
    public function showipbgpAction()
    {
        $backend = new Backend();
        $response = json_decode(trim($backend->configdRun("quagga diag-bgp2")));
        return array("response" => $response);
    }
    /**
     * show ip bgp summary
     * @return array
     */
    public function showipbgpsummaryAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("quagga diag-bgp summary");
        return array("response" => $response);
    }
    public function showrunningconfigAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("quagga general-runningconfig");
        return array("response" => $response);
    }
    private function get_ospf_information($name)
    {
        $backend = new Backend();
        return array("response" => json_decode(trim($backend->configdRun("quagga ospf-$name"))));
    }
    private function get_ospf3_information($name)
    {
        $backend = new Backend();
        return array("response" => json_decode(trim($backend->configdRun("quagga ospfv3-$name"))));
    }
    // OSPFv2
    public function ospfoverviewAction()
    {
        return $this->get_ospf_information('overview');
    }
    public function ospfneighborAction()
    {
        return $this->get_ospf_information('neighbor');
    }
    public function ospfrouteAction()
    {
        return $this->get_ospf_information('route');
    }
    public function ospfdatabaseAction()
    {
        return $this->get_ospf_information('database');
    }
    public function ospfinterfaceAction()
    {
        return $this->get_ospf_information('interface');
    }
    // OSPFv3
    public function ospfv3overviewAction()
    {
        return $this->get_ospf3_information('overview');
    }
    public function ospfv3neighborAction()
    {
        return $this->get_ospf3_information('neighbor');
    }
    public function ospfv3routeAction()
    {
        return $this->get_ospf3_information('route');
    }
    public function ospfv3databaseAction()
    {
        return $this->get_ospf3_information('database');
    }
    public function ospfv3interfaceAction()
    {
        return $this->get_ospf3_information('interface');
    }
    // General
    private function get_general_information($name)
    {
        $backend = new Backend();
        return array("response" => json_decode(trim($backend->configdRun("quagga general-$name")), true));
    }
    public function generalroutesAction()
    {
        return $this->get_general_information('routes');
    }
    public function logAction()
    {
        return $this->get_general_information('log')['response']['general_log'];
    }
    public function generalroutes6Action()
    {
        return $this->get_general_information('routes6');
    }
}
