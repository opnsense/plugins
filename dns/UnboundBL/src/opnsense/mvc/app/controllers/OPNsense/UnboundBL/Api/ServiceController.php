<?php
namespace OPNsense\UnboundBL\Api;
use OPNsense\Base\ApiMutableServiceControllerBase;
class ServiceController extends ApiMutableServiceControllerBase
{
    static protected $internalServiceClass = '\OPNsense\UnboundBL';
    static protected $internalServiceTemplate = 'OPNsense/UnboundBL';
    static protected $internalServiceEnabled = 'enabled';
    static protected $internalServiceName = 'UnboundBL';
    public function refreshAction()
    {
        $this->sessionClose();
        $mdl = new UnboundBL();
        $backend = new Backend();
        $response = $backend->configdpRun('UnboundBL refresh');
        return array("message" => $response);
    }
    public function reloadAction()
    {
        $this->sessionClose();
        $mdl = new UnboundBL();
        $backend = new Backend();
        $backend->configdRun("template reload OPNsense/UnboundBL");
        return;
    }
}
