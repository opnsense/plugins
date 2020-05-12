<?php
namespace OPNsense\RadSecProxy;
class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->basicForm = $this->getForm("basic");
        // pick the template to serve to our users.
        $this->view->pick('OPNsense/RadSecProxy/index');
    }
}