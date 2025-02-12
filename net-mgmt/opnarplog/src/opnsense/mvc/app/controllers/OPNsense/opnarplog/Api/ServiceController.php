<?php

/*
 * Copyright (C) 2024 github.com/mr-manuel
 * All rights reserved.
 */

namespace OPNsense\Opnarplog\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Opnarplog\General;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Opnarplog\General';
    protected static $internalServiceTemplate = 'OPNsense/Opnarplog';
    protected static $internalServiceEnabled = 'enabled';
    protected static $internalServiceName = 'opnarplog';

    /**
     * remove database folder
     * @return array
     */
    public function resetdbAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("opnarplog resetdb");
        return array("response" => $response);
    }

}
