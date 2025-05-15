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
class BouncersController extends ApiControllerBase
{
    /**
     * Retrieve list of bouncers
     *
     * @return array of bouncers
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function getAction()
    {
        $result = json_decode(trim((new Backend())->configdRun("crowdsec bouncers-list")), true);
        if ($result !== null) {
            // only return valid json type responses
            return $result;
        }
        return ["message" => "unable to list bouncers"];
    }
}
