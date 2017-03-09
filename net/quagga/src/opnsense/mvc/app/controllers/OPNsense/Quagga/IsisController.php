<?php
namespace OPNsense\Quagga;
class IsisController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->title = gettext("IS-IS-Settings");
        $this->view->generalForm = $this->getForm("isis");
        $this->view->pick('OPNsense/Quagga/isis');
    }
}
