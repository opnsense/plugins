<?php
namespace OPNsense\UnboundBL\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

class ServiceController extends ApiControllerBase
{
    
    public function reloadAction()
    {
            $backend = new Backend();
            $backend->configdRun("template reload OPNsense/UnboundBL");       
    }
    
    public function refreshAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
                $backend->configdRun("UnboundBL refresh");
                return array("message" => gettext("UnboundBL's lists have been updated! Please restart your Unbound DNS server."));
        }
        return array("message" => gettext("Something went wrong..."));
    }
    
}
