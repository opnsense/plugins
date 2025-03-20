<?php

namespace OPNsense\OpenApi\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

/**
 * Class ServiceController
 * @package OPNsense\OpenApi
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceName = 'openapi';
    protected static $internalServiceClass = '\OPNsense\OpenApi\OpenApi';
    protected static $internalServiceTemplate = 'OPNsense/OpenApi';
    protected static $internalServiceEnabled = 'general.enabled';

    /**
     * reconfigure with optional stop, generate config and start / reload
     * @return array response message
     * @throws \Exception when configd action fails
     * @throws \ReflectionException when model can't be instantiated
     */
    public function reconfigureAction()
    {
        $enabled = $this->serviceEnabled();
        if (!$enabled) {
            return ['status' => 'disabled'];
        }

        $backend = new Backend();
        return $backend->configdRun(escapeshellarg(static::$internalServiceName) . ' reconfigure');
    }

}
