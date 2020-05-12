<?php
namespace OPNsense\RadSecProxy;
class RealmsController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        //$this->view->generalForm = $this->getForm("realms");
        // pick the template to serve to our users.
        $this->view->pick('OPNsense/RadSecProxy/realms');
    }
}