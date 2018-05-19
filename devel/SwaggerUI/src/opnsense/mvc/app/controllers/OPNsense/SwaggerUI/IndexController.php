<?php

namespace OPNsense\SwaggerUI;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        // link rule dialog
        $this->view->formGeneralSettings = $this->getForm("general");
        // choose template
        $this->view->pick('OPNsense/SwaggerUI/index');
    }
}
