<?php
namespace OPNsense\Smart2\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

class ServiceController extends ApiControllerBase
{
    public function listAction ()
    {
        if ($this->request->isPost()) {
            exec("/bin/ls /dev | grep '^\(ad\|da\|ada\)[0-9]\{1,2\}$'", $devices);
            return array("devices" => $devices);
        }

        return array("message" => "unable to run list action");
    }
}
