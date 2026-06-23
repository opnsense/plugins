<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

/**
 * @package OPNsense\CrowdSec
 */
class MachinesController extends ApiControllerBase
{
    /**
     * Retrieve list of machines
     *
     * @return array of machines
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function searchAction(): array
    {
        $result = json_decode(trim((new Backend())->configdRun("crowdsec machines-list")), true);
        if ($result === null) {
            return ["message" => "unable to retrieve data"];
        }

        $rows = [];
        foreach ($result as $machine) {
            $rows[] = [
                'name' => $machine['machineId'],
                'ip_address' => $machine['ipAddress'] ?? '',
                'version' => $machine['version'] ?? '',
                'validated' => $machine['isValidated'] ?? false,
                'created' => $machine['created_at'] ?? '',
                'last_seen' => $machine['last_heartbeat'] ?? '',
                'os' => $machine['os'] ?? '',
            ];
        }

        return $this->searchRecordsetBase($rows);
    }
}
