<?php

namespace OPNsense\Bind;

class ReverseZonesController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->formDialogEditBindReverseDomain = $this->getForm('dialogEditBindReverseDomain');
        $this->view->pick('OPNsense/Bind/reverse_zones');
    }
}
