<?php
namespace OPNsense\Quagga;

class BgpController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->title = gettext("BGP-Settings");
        $this->view->generalForm = $this->getForm("bgp");
        $this->view->pick('OPNsense/Quagga/bgp');
    }
}
