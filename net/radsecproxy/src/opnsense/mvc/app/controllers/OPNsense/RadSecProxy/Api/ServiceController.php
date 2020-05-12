<?php
namespace OPNsense\RadSecProxy\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use \OPNsense\Core\Backend;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\RadSecProxy\RadSecProxy';
    protected static $internalServiceTemplate = 'OPNsense/RadSecProxy';
    protected static $internalServiceEnabled = 'general.Enabled';
    protected static $internalServiceName = 'radsecproxy';

    protected function reconfigureForceRestart()
    {
        return 0;
    }

    // public function reloadAction()
    // {
    //     $status = "failed";
    //     if ($this->request->isPost()) {
    //         $backend = new Backend();
    //         $bckresult = trim($backend->configdRun("template reload OPNsense/RadSecProxy"));
    //         if ($bckresult == "OK") {
    //             $status = "ok";
    //         }
    //     }
    //     return array("status" => $status);
    // }
}