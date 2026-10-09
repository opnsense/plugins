<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

namespace OPNsense\CrowdSec\Api;

use OPNsense\Base\ApiControllerBase;
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
     * Retrieve detailed information for a single alert
     *
     * @param string $alert_id Alert ID to inspect
     * @return array Alert details
     */
    public function getAction($alert_id): array
    {
        if (!ctype_digit(strval($alert_id))) {
            return ["message" => "invalid alert id"];
        }

        $backend = new Backend();
        $result = json_decode(trim($backend->configdRun("crowdsec alerts-inspect {$alert_id}")), true);
        if ($result === null) {
            return ["message" => "unable to retrieve alert details"];
        }

        $source = $result['source'] ?? [];

        $decisions = [];
        foreach ($result['decisions'] ?? [] as $dec) {
            $decisions[] = [
                'id'       => $dec['id'] ?? '',
                'scope'    => ($dec['scope'] ?? '') . ':' . ($dec['value'] ?? ''),
                'type'     => $dec['type'] ?? '',
                'duration' => $dec['duration'] ?? '',
                'origin'   => $dec['origin'] ?? '',
            ];
        }

        $events = [];
        foreach ($result['events'] ?? [] as $evt) {
            $meta = [];
            foreach ($evt['meta'] ?? [] as $m) {
                $meta[$m['key'] ?? ''] = $m['value'] ?? '';
            }
            $events[] = [
                'timestamp' => $evt['timestamp'] ?? '',
                'meta'      => $meta,
            ];
        }

        return [
            'alert' => [
                'id'           => $result['id'] ?? '',
                'created_at'   => $result['created_at'] ?? '',
                'machine_id'   => $result['machine_id'] ?? '',
                'simulated'    => $result['simulated'] ?? false,
                'remediation'  => $result['remediation'] ?? false,
                'scenario'     => $result['scenario'] ?? '',
                'message'      => $result['message'] ?? '',
                'events_count' => $result['events_count'] ?? 0,
                'scope_value'  => $this->formatScopeValue($source),
                'country'      => $source['cn'] ?? '',
                'as_name'      => $source['as_name'] ?? '',
                'as_number'    => $source['as_number'] ?? '',
                'ip_range'     => $source['range'] ?? '',
                'start_at'     => $result['start_at'] ?? '',
                'stop_at'      => $result['stop_at'] ?? '',
                'uuid'         => $result['uuid'] ?? '',
                'decisions'    => $decisions,
                'events'       => $events,
            ],
        ];
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
            $source = $alert['source'] ?? [];
            $rows[] = [
                'id'          => $alert['id'],
                'value'       => $this->formatScopeValue($source ?? []),
                'reason'      => $alert['scenario'] ?? '',
                'country'     => $source['cn'] ?? '',
                'as'          => $source['as_name'] ?? '',
                'decisions'   => $this->formatDecisions($alert['decisions'] ?? []),
                'created'  => $alert['created_at'] ?? '',
            ];
        }

        return $this->searchRecordsetBase($rows);
    }
}
