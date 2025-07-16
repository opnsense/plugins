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
class AlertsController extends ApiControllerBase
{
    /**
    * Format scope and value as "scope:value"
    *
    * @param array $source Array with 'scope' and 'value' keys (can be a decision)
    * @return string Formatted string
    */
    private function formatScopeValue(array $source): string
    {
        $scope = $source['scope'] ?? '';
        if ($source['value'] !== '') {
            $scope = $scope . ':' . $source['value'];
        }
        return $scope;
    }

    /**
     * Summarize decision types as "type1:count1 type2:count2 ..."
     *
     * @param array $decisions List of decision arrays
     * @return string Summary string
     */
    private function formatDecisions(array $decisions): string
    {
        $counts = [];

        foreach ($decisions as $decision) {
            if (!isset($decision['type'])) {
                continue;
            }

            $type = $decision['type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        $parts = [];
        foreach ($counts as $type => $count) {
            $parts[] = "{$type}:{$count}";
        }

        return implode(' ', $parts);
    }

    /**
     * Retrieve list of alerts
     *
     * @return array of alerts
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function searchAction(): array
    {
        $result = json_decode(trim((new Backend())->configdRun("crowdsec alerts-list")), true);
        if ($result === null) {
            return ["message" => "unable to retrieve data"];
        }

        $rows = [];
        foreach ($result as $alert) {
            $rows[] = [
                'id'          => $alert['id'],
                'value'       => $this->formatScopeValue($alert['source']),
                'reason'      => $alert['scenario'],
                'country'     => $alert['source']['cn'],
                'as'          => $alert['source']['as_name'],
                'decisions'   => $this->formatDecisions($alert['decisions'] ?? []),
                'created'  => $alert['created_at'],
            ];
        }

        return $this->searchRecordsetBase($rows);
    }
}
