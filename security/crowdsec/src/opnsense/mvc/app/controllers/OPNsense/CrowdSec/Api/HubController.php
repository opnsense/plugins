<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\CrowdSec\CrowdSec;
use OPNsense\Core\Backend;

/**
 * @package OPNsense\CrowdSec
 */
class HubController extends ApiControllerBase
{
    /**
     * retrieve the registered hub items
     * @return dictionary of items, by type
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function getAction()
    {
        $backend = new Backend();
        $bckresult = json_decode(trim($backend->configdRun("crowdsec hub-items")), true);
        if ($bckresult !== null) {
            // only return valid json type responses
            return $bckresult;
        }
        return array("message" => "unable to list hub items");
    }
}
