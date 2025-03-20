<?php

namespace OPNsense\OpenApi;

/**
 * Class IndexController
 * @package OPNsense\OpenApi
 */
class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        // pick the template to serve to our users.
        $this->view->pick('OPNsense/OpenApi/index');
        // fetch form data "general" in
        $this->view->generalForm = $this->getForm("general");
    }
}
