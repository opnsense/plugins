<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

/**
 * @package OPNsense\CrowdSec
 */
class VersionController extends ApiControllerBase
{
    /**
     * Retrieve version description
     *
     * @return version description
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function getAction(): string
    {
        return (new Backend())->configdRun("crowdsec version");
    }
}
