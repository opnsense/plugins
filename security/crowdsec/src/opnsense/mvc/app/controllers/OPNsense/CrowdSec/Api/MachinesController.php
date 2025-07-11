<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\CrowdSec\CrowdSec;
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
        $rows = json_decode(trim((new Backend())->configdRun("crowdsec machines-list")), true);
        if ($rows === null) {
            return ["message" => "unable to retrieve data"];
        }

        $total = sizeof($rows);
        return [
            "total" => $total,
            "rowCount" => $total,
            "current" => 1,
            "rows" => $rows
        ];
    }
}
