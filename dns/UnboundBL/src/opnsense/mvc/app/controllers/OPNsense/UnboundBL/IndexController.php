<?php
namespace OPNsense\UnboundBL;
class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->general = $this->getForm("general");
        $this->view->pick('OPNsense/UnboundBL/index');
    }
}
