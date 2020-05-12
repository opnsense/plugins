<?php
namespace OPNsense\RadSecProxy;
class TlsController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        //$this->view->generalForm = $this->getForm("servers");
        // pick the template to serve to our users.
        $this->view->pick('OPNsense/RadSecProxy/tls');
        $this->view->formDialogTls = $this->getForm("dialogTls");
    }
}