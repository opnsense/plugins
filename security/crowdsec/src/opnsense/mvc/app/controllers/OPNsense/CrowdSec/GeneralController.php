<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec;

/**
 * Class GeneralController
 * @package OPNsense\CrowdSec
 */
class GeneralController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/CrowdSec/general');
        $this->view->generalForm = $this->getForm("general");
    }
}
