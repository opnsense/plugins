<?php
namespace OPNsense\RadSecProxy;
class ServersController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        //$this->view->generalForm = $this->getForm("servers");
        // pick the template to serve to our users.
        $this->view->pick('OPNsense/RadSecProxy/servers');
        $this->view->formDialogServer = $this->getForm("dialogServer");
    }
}