<?php

namespace OPNsense\netbird;

/**
 * Class IndexController
 * @package OPNsense\netbird
 */
class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->generalForm = $this->getForm("general");
        $this->view->initialUpForm = $this->getForm("initialup");
        $this->view->pick('OPNsense/netbird/index');
    }
}
