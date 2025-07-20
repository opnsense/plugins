<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;


function unrollDecisions(array $alerts): array
{
    $result = [];

    foreach ($alerts as $alert) {
        if (!isset($alert['decisions']) || !is_array($alert['decisions'])) {
            continue;
        }

        foreach ($alert['decisions'] as $decision) {
            // ignore deleted decisions
            if (isset($decision['duration']) && str_starts_with($decision['duration'], '-')) {
                continue;
            }

            $row = $decision;

            // Add parent alert fields with prefix
            foreach ($alert as $key => $value) {
                if ($key === 'decisions') {
                    continue; // skip nested array
                }
                $row["alert_" . $key] = $value;
            }

            $result[] = $row;
        }
    }

    return $result;
}


/**
 * @package OPNsense\CrowdSec
 */
class DecisionsController extends ApiControllerBase
{
    /**
    * Format scope and value as "scope:value"
    *
    * @param array $source Array with 'scope' and 'value' keys
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
     * Retrieve list of decisions
     *
     * @return array of decisions
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    public function searchAction(): array
    {
        $result = json_decode(trim((new Backend())->configdRun("crowdsec decisions-list")), true);
        if ($result === null) {
            return ["message" => "unable to retrieve data"];
        }

        $decisions = unrollDecisions($result);

        $rows = [];
        foreach ($decisions as $dec) {
            $alert_source = $dec['alert_source'] ?? [];

            $rows[] = [
                'id'           => $dec['id'],
                'source'       => $dec['origin'] ?? '',
                'scope_value'  => $this->formatScopeValue($dec),
                'reason'       => $dec['scenario'] ?? '',
                'action'       => $dec['type'] ?? '',
                'country'      => $alert_source['cn'] ?? '',
                'as'           => $alert_source['as_name'] ?? '',
                'events_count' => $dec['alert_events_count'] ?? '',
                'expiration'   => $dec['duration'] ?? '',
                'alert_id'     => $dec['alert_id'],
            ];
        }

        return $this->searchRecordsetBase($rows);
    }

    public function delAction($decision_id): array
    {
        if ($this->request->isPost()) {
            $result = (new Backend())->configdRun("crowdsec decisions-delete {$decision_id}");
            if ($result === null) {
                return ["result" => "deleted"];
            }

            // why does the action return \n\n for empty output?
            if (trim($result) === '') {
                return ["result" => "deleted"];
            }
            // TODO assume not found, should handle other errors
            return ["result" => "not found"];
        } else {
            $this->response->setStatusCode(405, "Method Not Allowed");
            $this->response->setHeader("Allow", "DELETE");
        }
    }
}
