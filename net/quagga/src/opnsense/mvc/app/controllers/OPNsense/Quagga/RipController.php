<?php
namespace OPNsense\Quagga;

class RipController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->title = gettext("RIP Settings");
        $this->view->ripForm = $this->getForm("rip");
        $this->view->pick('OPNsense/Quagga/rip');
    }
}
