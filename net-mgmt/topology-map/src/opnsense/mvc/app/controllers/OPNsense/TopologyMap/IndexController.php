<?php

namespace OPNsense\TopologyMap;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->settings = $this->getForm('settings');
        $this->view->pick('OPNsense/TopologyMap/index');
    }
}
