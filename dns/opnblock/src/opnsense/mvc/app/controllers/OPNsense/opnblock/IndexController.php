<?php
namespace OPNsense\opnblock;
class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->general = $this->getForm("general");
        $this->view->pick('OPNsense/opnblock/index');

        $this->view->generalForm = $this->getForm("general");
    }
}
