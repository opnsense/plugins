<?php
namespace OPNsense\Quagga;
class GeneralController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->title = gettext("Routing-Settings");
        $this->view->generalForm = $this->getForm("general");
        $this->view->pick('OPNsense/Quagga/general');
    }
}
