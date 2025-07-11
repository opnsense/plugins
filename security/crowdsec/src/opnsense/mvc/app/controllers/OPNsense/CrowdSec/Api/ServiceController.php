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

        $crowdsec_status = "unknown";
        if (strpos($response, "not running") !== false) {
            $crowdsec_status = "stopped";
        } elseif (strpos($response, "is running") !== false) {
            $crowdsec_status = "running";
        }

        $response = $backend->configdRun("crowdsec crowdsec-firewall-status");

        $firewall_status = "unknown";
        if (strpos($response, "not running") !== false) {
            $firewall_status = "stopped";
        } elseif (strpos($response, "is running") !== false) {
            $firewall_status = "running";
        }

        $status = "unknown";
        if ($crowdsec_status == $firewall_status) {
            $status = $crowdsec_status;
        }

        return [
            "status" => $status,
            "crowdsec-status" => $crowdsec_status,
            "crowdsec-firewall-status" => $firewall_status,
        ];
    }
}
