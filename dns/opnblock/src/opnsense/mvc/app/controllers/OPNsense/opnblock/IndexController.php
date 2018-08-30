<?php
namespace OPNsense\opnblock;
class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/opnblock/index');
        $this->view->general = $this->getForm("general");
    }
}
