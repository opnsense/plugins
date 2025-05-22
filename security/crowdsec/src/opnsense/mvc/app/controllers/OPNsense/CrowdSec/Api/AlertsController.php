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
class AlertsController extends ApiControllerBase
{
    /**
     * Retrieve list of alerts
     *
     * @return array of alerts
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function getAction()
    {
        $result = json_decode(trim((new Backend())->configdRun("crowdsec alerts-list")), true);
        if ($result !== null) {
            // only return valid json type responses
            return $result;
        }
        return ["message" => "unable to list alerts"];
    }
}
