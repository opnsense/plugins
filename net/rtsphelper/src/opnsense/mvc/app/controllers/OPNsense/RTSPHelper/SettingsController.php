<?php

namespace OPNsense\RTSPHelper;

use OPNsense\Base\IndexController;

class SettingsController extends IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/RTSPHelper/index');
        $this->view->formGeneral = $this->getForm("general");
        $this->view->formDialogHost = $this->getForm("dialog_host");
        $this->view->formDialogPermission = $this->getForm("dialog_permission");
    }
}
