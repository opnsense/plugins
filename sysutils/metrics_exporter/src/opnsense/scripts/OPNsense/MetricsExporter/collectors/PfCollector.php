<?php

/*
 * Copyright (C) 2026 Brendan Bank
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once 'config.inc';
require_once __DIR__ . '/../lib/prometheus.php';

class PfCollector
{
    public static function name(): string
    {
        return 'Firewall';
    }

    public static function defaultEnabled(): bool
    {
        return true;
    }

    /**
     * Collect pf statistics and return Prometheus exposition text.
     */
    public static function collect(): string
    {
        $backend = new \OPNsense\Core\Backend();
        $info = json_decode(
            trim($backend->configdpRun('filter diag info', ['info'])),
            true
        );
        $stateSize = self::parseStateSize(
            trim($backend->configdRun('filter diag state_size'))
        );

        $lines = [];

        // State table gauges
        $entries = $info['info']['state-table']['current-entries']['total'] ?? 0;
        $limit = $stateSize['limit'] ?? 0;

        $lines[] = '# HELP opnsense_pf_states Current number of pf state table entries.';
        $lines[] = '# TYPE opnsense_pf_states gauge';
        $lines[] = sprintf('opnsense_pf_states %d', $entries);

        $lines[] = '# HELP opnsense_pf_states_limit Hard limit on pf state table entries.';
        $lines[] = '# TYPE opnsense_pf_states_limit gauge';
        $lines[] = sprintf('opnsense_pf_states_limit %d', $limit);

        // State table counters
        $stateTable = $info['info']['state-table'] ?? [];
        $stateCounters = [
            'searches' => 'opnsense_pf_state_searches_total',
            'inserts' => 'opnsense_pf_state_inserts_total',
            'removals' => 'opnsense_pf_state_removals_total',
        ];
        foreach ($stateCounters as $key => $metric) {
            $val = $stateTable[$key]['total'] ?? 0;
            $lines[] = sprintf(
                '# HELP %s Total pf state table %s.',
                $metric,
                $key
            );
            $lines[] = sprintf('# TYPE %s counter', $metric);
            $lines[] = sprintf('%s %d', $metric, $val);
        }

        // PF counters
        $counters = $info['info']['counters'] ?? [];
        if (!empty($counters)) {
            $lines[] = '# HELP opnsense_pf_counter_total PF counter by type.';
            $lines[] = '# TYPE opnsense_pf_counter_total counter';
            foreach ($counters as $name => $data) {
                $lines[] = sprintf(
                    'opnsense_pf_counter_total{name="%s"} %d',
                    prom_escape($name),
                    $data['total'] ?? 0
                );
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Return status data for the UI.
     */
    public static function status(): array
    {
        $backend = new \OPNsense\Core\Backend();
        $info = json_decode(
            trim($backend->configdpRun('filter diag info', ['info'])),
            true
        );
        $stateSize = self::parseStateSize(
            trim($backend->configdRun('filter diag state_size'))
        );

        $rows = [];

        // State table summary
        $stateTable = $info['info']['state-table'] ?? [];
        $rows[] = [
            'section' => 'state_table',
            'label' => 'Current Entries',
            'value' => (string)($stateTable['current-entries']['total'] ?? 0),
        ];
        $rows[] = [
            'section' => 'state_table',
            'label' => 'Hard Limit',
            'value' => (string)($stateSize['limit'] ?? 0),
        ];
        $rows[] = [
            'section' => 'state_table',
            'label' => 'Searches',
            'value' => number_format(
                $stateTable['searches']['total'] ?? 0
            ),
        ];
        $rows[] = [
            'section' => 'state_table',
            'label' => 'Inserts',
            'value' => number_format(
                $stateTable['inserts']['total'] ?? 0
            ),
        ];
        $rows[] = [
            'section' => 'state_table',
            'label' => 'Removals',
            'value' => number_format(
                $stateTable['removals']['total'] ?? 0
            ),
        ];

        // PF counters
        $counters = $info['info']['counters'] ?? [];
        foreach ($counters as $name => $data) {
            $rows[] = [
                'section' => 'counters',
                'label' => $name,
                'value' => number_format($data['total'] ?? 0),
            ];
        }

        return ['type' => 'pf', 'name' => 'Firewall', 'rows' => $rows];
    }

    /**
     * Parse state size output from configd.
     * Format: "current 1234\nlimit 200000"
     */
    private static function parseStateSize(string $output): array
    {
        $result = ['current' => 0, 'limit' => 0];
        foreach (explode("\n", $output) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                if ($parts[0] === 'current') {
                    $result['current'] = (int)$parts[1];
                } elseif ($parts[0] === 'limit') {
                    $result['limit'] = (int)$parts[1];
                }
            }
        }
        return $result;
    }

}
