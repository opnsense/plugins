<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

/**
 * Class ServiceController
 * @package OPNsense\CrowdSec
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\CrowdSec\General';
    protected static $internalServiceTemplate = 'OPNsense/CrowdSec';
    protected static $internalServiceName = 'crowdsec';

    protected function ServiceEnabled()
    {
        $mdl = $this->getModel();

        return (
            $mdl->agent_enabled->__toString() === "1" ||
            $mdl->lapi_enabled->__toString() === "1" ||
            $mdl->firewall_bouncer_enabled->__toString() === "1"
        );
    }

    public function reconfigureAction()
    {
        // Run the default reconfigure logic
        $result = parent::reconfigureAction();

        // Now we generate the config.yaml and config-firewall-bouncer.yaml files
        if (isset($result['status']) && $result['status'] === 'ok') {
            $backend = new Backend();
            $backend->configdRun('crowdsec reconfigure');
        }

        return $result;
    }
}
