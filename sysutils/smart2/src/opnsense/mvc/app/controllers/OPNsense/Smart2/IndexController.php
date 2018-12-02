<?php
namespace OPNsense\Smart2;
class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        // pick the template to serve to our users.
        $this->view->pick('OPNsense/Smart2/index');
    }
}
