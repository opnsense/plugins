namespace OPNsense\UnboundBL\Api;
use OPNsense\Base\ApiMutableServiceControllerBase;
class ServiceController extends ApiMutableServiceControllerBase
{
    static protected $internalServiceClass = '\OPNsense\UnboundBL';
    static protected $internalServiceTemplate = 'OPNsense/UnboundBL';
    static protected $internalServiceEnabled = 'enabled';
    static protected $internalServiceName = 'UnboundBL';
    public function UnboundBLAction()
    {
        $this->sessionClose();
        $mdl = new UnboundBL();
        $backend = new Backend();
        $response = $backend->configdpRun('UnboundBL', array((string)$mdl->type));
        return array("response" => $response);
    }
}
