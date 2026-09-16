<?php

namespace OPNsense\TelegramNotify;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->settings = $this->getForm('settings');
        $this->view->pick('OPNsense/TelegramNotify/index');
    }
}
