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

class UnboundCollector
{
    public static function name(): string
    {
        return 'Unbound DNS';
    }

    public static function defaultEnabled(): bool
    {
        return true;
    }

    /**
     * Collect Unbound statistics and return Prometheus exposition text.
     */
    public static function collect(): string
    {
        $backend = new \OPNsense\Core\Backend();
        $raw = trim($backend->configdRun('unbound stats'));
        if ($raw === '' || $raw[0] !== '{') {
            return '';
        }
        $stats = json_decode($raw, true);
        if ($stats === null) {
            return '';
        }

        $lines = [];

        // Query counters
        $queryCounters = [
            'queries' => ['opnsense_unbound_queries_total', 'Total DNS queries received.'],
            'cachehits' => ['opnsense_unbound_cache_hits_total', 'Total cache hits.'],
            'cachemiss' => ['opnsense_unbound_cache_misses_total', 'Total cache misses.'],
            'prefetch' => ['opnsense_unbound_prefetch_total', 'Total prefetch actions.'],
            'recursivereplies' => [
                'opnsense_unbound_recursive_replies_total',
                'Total recursive replies.',
            ],
        ];
        foreach ($queryCounters as $key => [$metric, $help]) {
            $val = $stats['total']['num'][$key] ?? null;
            if ($val === null) {
                continue;
            }
            $lines[] = sprintf('# HELP %s %s', $metric, $help);
            $lines[] = sprintf('# TYPE %s counter', $metric);
            $lines[] = sprintf('%s %d', $metric, (int)$val);
        }

        // Answer rcode counters
        $rcodes = $stats['num']['answer']['rcode'] ?? [];
        if (!empty($rcodes)) {
            $lines[] = '# HELP opnsense_unbound_answer_rcode_total DNS answers by rcode.';
            $lines[] = '# TYPE opnsense_unbound_answer_rcode_total counter';
            foreach ($rcodes as $rcode => $val) {
                $lines[] = sprintf(
                    'opnsense_unbound_answer_rcode_total{rcode="%s"} %d',
                    prom_escape($rcode),
                    (int)$val
                );
            }
        }

        // Query type counters
        $qtypes = $stats['num']['query']['type'] ?? [];
        if (!empty($qtypes)) {
            $lines[] = '# HELP opnsense_unbound_query_type_total DNS queries by type.';
            $lines[] = '# TYPE opnsense_unbound_query_type_total counter';
            foreach ($qtypes as $qtype => $val) {
                $lines[] = sprintf(
                    'opnsense_unbound_query_type_total{type="%s"} %d',
                    prom_escape($qtype),
                    (int)$val
                );
            }
        }

        // Query opcode counters
        $opcodes = $stats['num']['query']['opcode'] ?? [];
        if (!empty($opcodes)) {
            $lines[] = '# HELP opnsense_unbound_query_opcode_total DNS queries by opcode.';
            $lines[] = '# TYPE opnsense_unbound_query_opcode_total counter';
            foreach ($opcodes as $opcode => $val) {
                $lines[] = sprintf(
                    'opnsense_unbound_query_opcode_total{opcode="%s"} %d',
                    prom_escape($opcode),
                    (int)$val
                );
            }
        }

        // Memory gauges
        $lines[] = '# HELP opnsense_unbound_memory_bytes Memory usage in bytes.';
        $lines[] = '# TYPE opnsense_unbound_memory_bytes gauge';
        $memCaches = ['rrset', 'message'];
        foreach ($memCaches as $cache) {
            $val = $stats['mem']['cache'][$cache] ?? null;
            if ($val !== null) {
                $lines[] = sprintf(
                    'opnsense_unbound_memory_bytes{cache="%s"} %d',
                    $cache,
                    (int)$val
                );
            }
        }
        $memMods = ['iterator', 'validator'];
        foreach ($memMods as $mod) {
            $val = $stats['mem']['mod'][$mod] ?? null;
            if ($val !== null) {
                $lines[] = sprintf(
                    'opnsense_unbound_memory_bytes{module="%s"} %d',
                    $mod,
                    (int)$val
                );
            }
        }
        $memOther = [
            'streamwait' => $stats['mem']['streamwait'] ?? null,
            'http.query_buffer' => $stats['mem']['http']['query_buffer'] ?? null,
            'http.response_buffer' => $stats['mem']['http']['response_buffer'] ?? null,
        ];
        foreach ($memOther as $label => $val) {
            if ($val !== null) {
                $lines[] = sprintf(
                    'opnsense_unbound_memory_bytes{type="%s"} %d',
                    $label,
                    (int)$val
                );
            }
        }

        // Request list
        $reqList = $stats['total']['requestlist'] ?? [];
        if (!empty($reqList)) {
            $lines[] = '# HELP opnsense_unbound_requestlist_avg Average request list size.';
            $lines[] = '# TYPE opnsense_unbound_requestlist_avg gauge';
            $lines[] = sprintf('opnsense_unbound_requestlist_avg %s', (float)($reqList['avg'] ?? 0));

            $lines[] = '# HELP opnsense_unbound_requestlist_max Maximum request list size.';
            $lines[] = '# TYPE opnsense_unbound_requestlist_max gauge';
            $lines[] = sprintf('opnsense_unbound_requestlist_max %d', (int)($reqList['max'] ?? 0));

            $lines[] = '# HELP opnsense_unbound_requestlist_overwritten_total '
                . 'Overwritten request list entries.';
            $lines[] = '# TYPE opnsense_unbound_requestlist_overwritten_total counter';
            $lines[] = sprintf(
                'opnsense_unbound_requestlist_overwritten_total %d',
                (int)($reqList['overwritten'] ?? 0)
            );

            $lines[] = '# HELP opnsense_unbound_requestlist_exceeded_total '
                . 'Exceeded request list entries.';
            $lines[] = '# TYPE opnsense_unbound_requestlist_exceeded_total counter';
            $lines[] = sprintf(
                'opnsense_unbound_requestlist_exceeded_total %d',
                (int)($reqList['exceeded'] ?? 0)
            );

            $current = $reqList['current']['all'] ?? $reqList['current'] ?? 0;
            $lines[] = '# HELP opnsense_unbound_requestlist_current Current request list size.';
            $lines[] = '# TYPE opnsense_unbound_requestlist_current gauge';
            $lines[] = sprintf('opnsense_unbound_requestlist_current %d', (int)$current);
        }

        // Recursion time
        $recTime = $stats['total']['recursion']['time'] ?? [];
        if (!empty($recTime)) {
            $lines[] = '# HELP opnsense_unbound_recursion_time_avg_seconds '
                . 'Average recursion time in seconds.';
            $lines[] = '# TYPE opnsense_unbound_recursion_time_avg_seconds gauge';
            $lines[] = sprintf(
                'opnsense_unbound_recursion_time_avg_seconds %s',
                (float)($recTime['avg'] ?? 0)
            );

            $lines[] = '# HELP opnsense_unbound_recursion_time_median_seconds '
                . 'Median recursion time in seconds.';
            $lines[] = '# TYPE opnsense_unbound_recursion_time_median_seconds gauge';
            $lines[] = sprintf(
                'opnsense_unbound_recursion_time_median_seconds %s',
                (float)($recTime['median'] ?? 0)
            );
        }

        // TCP usage
        $tcpUsage = $stats['total']['tcpusage'] ?? null;
        if ($tcpUsage !== null) {
            $lines[] = '# HELP opnsense_unbound_tcp_usage Current TCP buffer usage.';
            $lines[] = '# TYPE opnsense_unbound_tcp_usage gauge';
            $lines[] = sprintf('opnsense_unbound_tcp_usage %d', (int)$tcpUsage);
        }

        // DNSSEC counters
        $dnssecCounters = [
            'answer_secure' => [
                $stats['num']['answer']['secure'] ?? null,
                'opnsense_unbound_answer_secure_total',
                'DNSSEC secure answers.',
            ],
            'answer_bogus' => [
                $stats['num']['answer']['bogus'] ?? null,
                'opnsense_unbound_answer_bogus_total',
                'DNSSEC bogus answers.',
            ],
            'rrset_bogus' => [
                $stats['num']['rrset']['bogus'] ?? null,
                'opnsense_unbound_rrset_bogus_total',
                'DNSSEC bogus RRsets.',
            ],
        ];
        foreach ($dnssecCounters as [$val, $metric, $help]) {
            if ($val === null) {
                continue;
            }
            $lines[] = sprintf('# HELP %s %s', $metric, $help);
            $lines[] = sprintf('# TYPE %s counter', $metric);
            $lines[] = sprintf('%s %d', $metric, (int)$val);
        }

        // Unwanted traffic counters
        $unwantedCounters = [
            'queries' => [
                'opnsense_unbound_unwanted_queries_total',
                'Total unwanted queries.',
            ],
            'replies' => [
                'opnsense_unbound_unwanted_replies_total',
                'Total unwanted replies.',
            ],
        ];
        foreach ($unwantedCounters as $key => [$metric, $help]) {
            $val = $stats['unwanted'][$key] ?? null;
            if ($val === null) {
                continue;
            }
            $lines[] = sprintf('# HELP %s %s', $metric, $help);
            $lines[] = sprintf('# TYPE %s counter', $metric);
            $lines[] = sprintf('%s %d', $metric, (int)$val);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Return status data for the UI.
     */
    public static function status(): array
    {
        $backend = new \OPNsense\Core\Backend();
        $raw = trim($backend->configdRun('unbound stats'));
        if ($raw === '' || $raw[0] !== '{') {
            return ['type' => 'unbound', 'name' => 'Unbound DNS', 'rows' => []];
        }
        $stats = json_decode($raw, true);
        if ($stats === null) {
            return ['type' => 'unbound', 'name' => 'Unbound DNS', 'rows' => []];
        }

        $rows = [];
        $rows[] = [
            'label' => 'Total Queries',
            'value' => number_format((int)($stats['total']['num']['queries'] ?? 0)),
        ];
        $rows[] = [
            'label' => 'Cache Hits',
            'value' => number_format((int)($stats['total']['num']['cachehits'] ?? 0)),
        ];
        $rows[] = [
            'label' => 'Cache Misses',
            'value' => number_format((int)($stats['total']['num']['cachemiss'] ?? 0)),
        ];

        return ['type' => 'unbound', 'name' => 'Unbound DNS', 'rows' => $rows];
    }
}
