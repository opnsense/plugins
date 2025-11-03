<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\CrowdSec\Util;
use OPNsense\Core\Backend;

/**
 * @package OPNsense\CrowdSec
 */
class PostoverflowsController extends ApiControllerBase
{
    /**
     * Retrieve the installed postoverflows
     *
     * @return dictionary of items, by type
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function searchAction(): array
    {
        $result = json_decode(trim((new Backend())->configdRun("crowdsec postoverflows-list")), true);
        if ($result === null) {
            return ["message" => "unable to retrieve data"];
        }

        $items = $result["postoverflows"];

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'name' => $item['name'],
                'status' => $item['status'] ?? '',
                'local_version' => $item['local_version'] ?? '',
                'local_path' => Util::trimLocalPath($item['local_path'] ?? ''),
                'description' => $item['description'] ?? '',
            ];
        }

        return $this->searchRecordsetBase($rows);
    }
}
