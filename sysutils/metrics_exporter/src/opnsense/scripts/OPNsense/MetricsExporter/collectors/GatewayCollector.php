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

require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/../lib/prometheus.php';

class GatewayCollector
{
    public static function name(): string
    {
        return 'Gateways';
    }

    public static function defaultEnabled(): bool
    {
        return true;
    }

    /**
     * Collect gateway metrics and return Prometheus exposition text.
     */
    public static function collect(): string
    {
        $metrics = self::collectMetrics();
        return self::formatPrometheus($metrics);
    }

    /**
     * Return status data for the UI.
     */
    public static function status(): array
    {
        $gateways = self::fetchGateways();
        if ($gateways === null) {
            return ['type' => 'gateway', 'name' => 'Gateways', 'rows' => []];
        }

        $rows = [];

        foreach ($gateways as $gw) {
            $gname = $gw['name'] ?? 'unknown';
            $entry = [
                'name' => $gname,
                'description' => $gname,
                'status' => $gw['status'] ?? 'pending',
                'status_translated' => $gw['status_translated'] ?? 'Pending',
                'delay' => $gw['delay'] ?? '~',
                'stddev' => $gw['stddev'] ?? '~',
                'loss' => $gw['loss'] ?? '~',
                'monitor' => ($gw['monitor'] ?? '~') !== '~' ? $gw['monitor'] : '',
            ];

            $rows[] = $entry;
        }

        return ['type' => 'gateway', 'name' => 'Gateways', 'rows' => $rows];
    }

    /**
     * Fetch gateway status via configd Backend.
     */
    private static function fetchGateways(): ?array
    {
        try {
            $backend = new \OPNsense\Core\Backend();
            $raw = trim($backend->configdRun('interface gateways status'));
            $gateways = json_decode($raw, true);
            if (!is_array($gateways)) {
                syslog(
                    LOG_WARNING,
                    'Metrics exporter: invalid gateway status JSON from configd'
                );
                return null;
            }
            return $gateways;
        } catch (\Throwable $e) {
            syslog(
                LOG_WARNING,
                'Metrics exporter: error reading gateway status: ' . $e->getMessage()
            );
            return null;
        }
    }

    /**
     * Collect gateway metrics using configd Backend.
     */
    private static function collectMetrics(): array
    {
        $gateways = self::fetchGateways();
        if ($gateways === null) {
            return [];
        }

        $metrics = [];

        foreach ($gateways as $gw) {
            $gname = $gw['name'] ?? 'unknown';
            $entry = [
                'name' => $gname,
                'description' => $gname,
                'monitor' => ($gw['monitor'] ?? '~') !== '~' ? $gw['monitor'] : '',
            ];

            $delay_ms = 0.0;
            if (($gw['delay'] ?? '~') !== '~') {
                $delay_ms = (float)$gw['delay'];
            }
            $entry['delay_seconds'] = $delay_ms / 1000.0;

            $stddev_ms = 0.0;
            if (($gw['stddev'] ?? '~') !== '~') {
                $stddev_ms = (float)$gw['stddev'];
            }
            $entry['stddev_seconds'] = $stddev_ms / 1000.0;

            $loss_pct = 0.0;
            if (($gw['loss'] ?? '~') !== '~') {
                $loss_pct = (float)$gw['loss'];
            }
            $entry['loss_ratio'] = $loss_pct / 100.0;

            $status = $gw['status'] ?? '';
            switch ($status) {
                case 'none':
                    $entry['status_num'] = 1;
                    $entry['status_text'] = 'online';
                    break;
                case 'down':
                    $entry['status_num'] = 0;
                    $entry['status_text'] = 'down';
                    break;
                case 'force_down':
                    $entry['status_num'] = 0;
                    $entry['status_text'] = 'force_down';
                    break;
                case 'loss':
                    $entry['status_num'] = 2;
                    $entry['status_text'] = 'loss';
                    break;
                case 'delay':
                    $entry['status_num'] = 3;
                    $entry['status_text'] = 'delay';
                    break;
                case 'delay+loss':
                    $entry['status_num'] = 4;
                    $entry['status_text'] = 'delay+loss';
                    break;
                default:
                    $entry['status_num'] = 5;
                    $entry['status_text'] = 'unknown';
                    break;
            }

            $metrics[] = $entry;
        }

        return $metrics;
    }

    /**
     * Format metrics array as Prometheus exposition text.
     */
    private static function formatPrometheus(array $metrics): string
    {
        $lines = [];

        $lines[] = '# HELP opnsense_gateway_status Gateway status'
            . ' (0=down, 1=up, 2=loss, 3=delay, 4=delay+loss, 5=unknown).';
        $lines[] = '# TYPE opnsense_gateway_status gauge';
        foreach ($metrics as $m) {
            $lines[] = sprintf(
                'opnsense_gateway_status{name="%s",description="%s"} %d',
                prom_escape($m['name']),
                prom_escape($m['description']),
                $m['status_num']
            );
        }

        $lines[] = '# HELP opnsense_gateway_delay_seconds'
            . ' Gateway round-trip time in seconds.';
        $lines[] = '# TYPE opnsense_gateway_delay_seconds gauge';
        foreach ($metrics as $m) {
            $lines[] = sprintf(
                'opnsense_gateway_delay_seconds{name="%s",description="%s"} %.6f',
                prom_escape($m['name']),
                prom_escape($m['description']),
                $m['delay_seconds']
            );
        }

        $lines[] = '# HELP opnsense_gateway_stddev_seconds'
            . ' Gateway RTT standard deviation in seconds.';
        $lines[] = '# TYPE opnsense_gateway_stddev_seconds gauge';
        foreach ($metrics as $m) {
            $lines[] = sprintf(
                'opnsense_gateway_stddev_seconds{name="%s",description="%s"} %.6f',
                prom_escape($m['name']),
                prom_escape($m['description']),
                $m['stddev_seconds']
            );
        }

        $lines[] = '# HELP opnsense_gateway_loss_ratio'
            . ' Gateway packet loss ratio (0.0-1.0).';
        $lines[] = '# TYPE opnsense_gateway_loss_ratio gauge';
        foreach ($metrics as $m) {
            $lines[] = sprintf(
                'opnsense_gateway_loss_ratio{name="%s",description="%s"} %.4f',
                prom_escape($m['name']),
                prom_escape($m['description']),
                $m['loss_ratio']
            );
        }

        $lines[] = '# HELP opnsense_gateway_info'
            . ' Gateway informational metric with status and monitor labels.';
        $lines[] = '# TYPE opnsense_gateway_info gauge';
        foreach ($metrics as $m) {
            $lines[] = sprintf(
                'opnsense_gateway_info{name="%s",description="%s",'
                    . 'status="%s",monitor="%s"} 1',
                prom_escape($m['name']),
                prom_escape($m['description']),
                prom_escape($m['status_text']),
                prom_escape($m['monitor'])
            );
        }

        return implode("\n", $lines) . "\n";
    }
}
