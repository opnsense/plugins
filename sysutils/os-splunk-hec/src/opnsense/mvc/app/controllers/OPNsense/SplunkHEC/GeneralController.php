<?php

/*
 * Copyright (C) 2026 pvols79
 * All rights reserved.
 *
 * SPDX-License-Identifier: BSD-2-Clause
 */

namespace OPNsense\SplunkHEC;

class GeneralController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/SplunkHEC/index');
    }
}
