<?php
namespace OPNsense\Quagga;
class OspfController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->title = gettext("OSPF-Settings");
        $this->view->generalForm = $this->getForm("ospf");
        $this->view->formDialogEditNetwork = $this->getForm("dialogEditOSPFNetwork");
        $this->view->pick('OPNsense/Quagga/ospf');
    }
}
