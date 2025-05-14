<?php

/*
 * Copyright (C) 2025 github.com/mr-manuel
 * All rights reserved.
 *
 * License: BSD 2-Clause
 */

namespace OPNsense\ArpNdpLogging\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\ArpNdpLogging\General;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\ArpNdpLogging\General';
    protected static $internalServiceTemplate = 'OPNsense/ArpNdpLogging';
    protected static $internalServiceEnabled = 'enabled';
    protected static $internalServiceName = 'arpndplogging';

    /**
     * remove database folder
     * @return array
     */
    public function resetdbAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("arpndplogging resetdb");
        return array("response" => $response);
    }

}
