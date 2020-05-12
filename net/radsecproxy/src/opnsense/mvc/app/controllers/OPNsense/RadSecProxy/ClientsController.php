<?php

namespace OPNsense\RadSecProxy;

class ClientsController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        //$this->view->generalForm = $this->getForm("clients");
        // pick the template to serve to our users.
        $this->view->pick('OPNsense/RadSecProxy/clients');
        $this->view->formDialogClient = $this->getForm("dialogClient");
    }
}