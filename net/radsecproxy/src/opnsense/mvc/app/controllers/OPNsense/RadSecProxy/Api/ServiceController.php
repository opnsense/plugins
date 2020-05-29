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

    public function checkconfigAction()
    {
        $backend = new Backend();
        // first generate template based on current configuration
        $backend->configdRun('template reload OPNsense/RadSecProxy');
        // now export all the required files (or syntax check will fail)
        $backend->configdRun("radsecproxy setup");
        // finally run the syntax check
        $response = $backend->configdRun("radsecproxy checkconfig");

        return array("result" => trim($response));
    }

}