<?php

namespace OPNsense\ProxyUserACL;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        // set page title, used by the standard template in layouts/default.volt.
        $this->view->title = gettext("Group and User ACL settings");
        // pick the template to serve to our users.
        $this->view->pick('OPNsense/ProxyUserACL/index');
        $this->view->formDialogACL = $this->getForm("dialogACL");
    }
}
