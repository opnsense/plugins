<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\DynDNS\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\DynDNS\CheckIP;
use OPNsense\Core\Config;

/**
 * Class TestController Handles test settings related API actions for the Check IP module
 * @package OPNsense\CheckIP
 */
class CheckIPTestController extends ApiControllerBase
{
    /**
     * retrieve CheckIP test settings
     * @param $nodeType string
     * @return test settings array
     */
    public function getAction($nodeType = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isGet() && $nodeType == 'service') {

            $mdlCheckIPService = new CheckIP();

            // Get the factory default test settings.
            $so = $mdlCheckIPService->factory_default_test->getNodes();

            // Get the check IP services.
            $nodes = $mdlCheckIPService->$nodeType->getNodes();

            // Include the factory default check IP service.
            $nodes['FDS'] = $mdlCheckIPService->factory_default_service->getNodes();

            // Populate the service options.
            if (is_array($nodes)) {
                foreach ($nodes as $nodeUuid => $node) {
                    $servicename = $node['name'];
                    $selected = ($node['default'] == "1") ? 1 : 0;

                    $suffix1 = ($nodeUuid == "FDS") ? " (" . gettext("FDS") . ")" : "";
                    $suffix2 = ($node['default'] == "1") ? " (" . gettext("default") . ")" : "";

                    $so[$nodeType][$servicename]['value'] = $servicename . $suffix1 . $suffix2;
                    $so[$nodeType][$servicename]['selected'] = $selected;
                }
            }

            $result['checkip']['test'] = $so;
            $result['result'] = 'ok';
        }
        return $result;
    }
}
