<?php
/* SPDX-License-Identifier: BSD-2-Clause */
namespace OPNsense\Newt;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->settingsForm = $this->getForm('settings');
        $this->view->pick('OPNsense/Newt/index');
    }
}
