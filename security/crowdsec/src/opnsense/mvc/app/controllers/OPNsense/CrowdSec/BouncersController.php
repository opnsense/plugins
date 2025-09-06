<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec;

/**
 * Class BouncersController
 * @package OPNsense\CrowdSec
 */
class BouncersController extends \OPNsense\Base\IndexController
{
    public function indexAction(): void
    {
        $this->view->pick('OPNsense/CrowdSec/bouncers');
    }
}
