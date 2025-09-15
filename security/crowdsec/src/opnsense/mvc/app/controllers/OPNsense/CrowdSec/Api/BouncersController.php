<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

/**
 * @package OPNsense\CrowdSec
 */
class BouncersController extends ApiControllerBase
{
    /**
     * Retrieve list of bouncers
     *
     * @return array of bouncers
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function searchAction(): array
    {
        $result = json_decode(trim((new Backend())->configdRun("crowdsec bouncers-list")), true);
        if ($result === null) {
            return ["message" => "unable to retrieve data"];
        }

        $rows = [];
        foreach ($result as $bouncer) {
            $rows[] = [
                'name' => $bouncer['name'],
                'type' => $bouncer['type'] ?? '',
                'version' => $bouncer['version'] ?? '',
                'created' => $bouncer['created_at'] ?? '',
                'valid' => ($bouncer['revoked'] ?? false) !== true,
                'ip_address' => $bouncer['ip_address'] ?? '',
                'last_seen' => $bouncer['last_pull'] ?? '',
                'os' => $bouncer['os'] ?? '',
            ];
        }

        return $this->searchRecordsetBase($rows);
    }
}
