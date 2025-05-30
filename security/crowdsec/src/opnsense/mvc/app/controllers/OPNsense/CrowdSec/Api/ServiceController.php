<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

/**
 * Class ServiceController
 * @package OPNsense\CrowdSec
 */
class ServiceController extends ApiControllerBase
{
    /**
     * reconfigure CrowdSec
     */
    public function reloadAction()
    {
        $status = "failed";
        if ($this->request->isPost()) {
            $backend = new Backend();
            $bckresult = trim($backend->configdRun('template reload OPNsense/CrowdSec'));
            if ($bckresult == "OK") {
                $bckresult = trim($backend->configdRun('crowdsec reconfigure'));
                if ($bckresult == "OK") {
                    $status = "ok";
                }
            }
        }
        return ["status" => $status];
    }

    /**
     * Retrieve status of crowdsec
     *
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("crowdsec crowdsec-status");

        $status = "unknown";
        if (strpos($response, "not running") > 0) {
            $status = "stopped";
        } elseif (strpos($response, "is running") > 0) {
            $status = "running";
        }

        $response = $backend->configdRun("crowdsec crowdsec-firewall-status");

        $firewall_status = "unknown";
        if (strpos($response, "not running") > 0) {
            $firewall_status = "stopped";
        } elseif (strpos($response, "is running") > 0) {
            $firewall_status = "running";
        }

        return [
            "crowdsec-status" => $status,
            "crowdsec-firewall-status" => $firewall_status,
        ];
    }
}
