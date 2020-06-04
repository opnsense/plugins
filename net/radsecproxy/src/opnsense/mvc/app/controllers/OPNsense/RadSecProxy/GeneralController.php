<?php

namespace OPNsense\RadSecProxy;

class GeneralController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->generalForm = $this->getForm("general");
// pick the template to serve to our users.
        $this->view->pick('OPNsense/RadSecProxy/general');
    }
}
