<?php

namespace OPNsense\netbird;

/**
 * Class ConstatusController
 * @package OPNsense\netbird
 */
class ConstatusController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/netbird/constatus');
    }
}
