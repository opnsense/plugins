<?php

/*
 * Copyright (C) 2024 github.com/mr-manuel
 * All rights reserved.
 */

namespace OPNsense\Opnarplog;

class GeneralController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->generalForm = $this->getForm('general');
        $this->view->pick('OPNsense/Opnarplog/general');
    }
}
