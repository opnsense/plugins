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
require_once 'util.inc';
require_once 'interfaces.inc';
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
        try {
            $gateways_status = return_gateways_status();
            $gateways_config = (new \OPNsense\Routing\Gateways())->gatewaysIndexedByName();
        } catch (\Error $e) {
            return ['type' => 'gateway', 'name' => 'Gateways', 'rows' => []];
        }

        $rows = [];

        foreach ($gateways_config as $gname => $gw) {
            $entry = [
                'name' => $gname,
                'description' => !empty($gw['descr']) ? $gw['descr'] : $gname,
            ];

            if (!empty($gateways_status[$gname])) {
                $gs = $gateways_status[$gname];
                $entry['status'] = $gs['status'];
                $entry['delay'] = $gs['delay'];
                $entry['stddev'] = $gs['stddev'];
                $entry['loss'] = $gs['loss'];
                $entry['monitor'] = $gs['monitor'] !== '~' ? $gs['monitor'] : '';

                switch ($gs['status']) {
                    case 'none':
                        $entry['status_translated'] = 'Online';
                        break;
                    case 'force_down':
                        $entry['status_translated'] = 'Offline (forced)';
                        break;
                    case 'down':
                        $entry['status_translated'] = 'Offline';
                        break;
                    case 'delay':
                        $entry['status_translated'] = 'Latency';
                        break;
                    case 'loss':
                        $entry['status_translated'] = 'Packetloss';
                        break;
                    case 'delay+loss':
                        $entry['status_translated'] = 'Latency, Packetloss';
                        break;
                    default:
                        $entry['status_translated'] = 'Pending';
                        break;
                }
            } else {
                $entry['status'] = 'pending';
                $entry['status_translated'] = 'Pending';
                $entry['delay'] = '~';
                $entry['stddev'] = '~';
                $entry['loss'] = '~';
                $entry['monitor'] = '';
            }

            $rows[] = $entry;
        }

        return ['type' => 'gateway', 'name' => 'Gateways', 'rows' => $rows];
    }

    /**
     * Collect gateway metrics using the OPNsense gateway API.
     */
    private static function collectMetrics(): array
    {
        try {
            $gateways_status = return_gateways_status();
            $gateways_config = (new \OPNsense\Routing\Gateways())->gatewaysIndexedByName();
        } catch (\Error $e) {
            syslog(
                LOG_WARNING,
                'Metrics exporter: error reading gateway status: ' . $e->getMessage()
            );
            return [];
        }

        $metrics = [];

        foreach ($gateways_config as $gname => $gw) {
            $entry = [
                'name' => $gname,
                'description' => !empty($gw['descr']) ? $gw['descr'] : $gname,
            ];

            if (!empty($gateways_status[$gname])) {
                $gs = $gateways_status[$gname];
                $entry['status_text'] = $gs['status'];
                $entry['monitor'] = $gs['monitor'] !== '~' ? $gs['monitor'] : '';

                $delay_ms = 0.0;
                if ($gs['delay'] !== '~') {
                    $delay_ms = (float)$gs['delay'];
                }
                $entry['delay_seconds'] = $delay_ms / 1000.0;

                $stddev_ms = 0.0;
                if ($gs['stddev'] !== '~') {
                    $stddev_ms = (float)$gs['stddev'];
                }
                $entry['stddev_seconds'] = $stddev_ms / 1000.0;

                $loss_pct = 0.0;
                if ($gs['loss'] !== '~') {
                    $loss_pct = (float)$gs['loss'];
                }
                $entry['loss_ratio'] = $loss_pct / 100.0;

                switch ($gs['status']) {
                    case 'none':
                        $entry['status_num'] = 1;
                        break;
                    case 'down':
                    case 'force_down':
                        $entry['status_num'] = 0;
                        break;
                    case 'loss':
                        $entry['status_num'] = 2;
                        break;
                    case 'delay':
                        $entry['status_num'] = 3;
                        break;
                    case 'delay+loss':
                        $entry['status_num'] = 4;
                        break;
                    default:
                        $entry['status_num'] = 5;
                        break;
                }
            } else {
                $entry['status_text'] = 'pending';
                $entry['status_num'] = 5;
                $entry['monitor'] = '';
                $entry['delay_seconds'] = 0.0;
                $entry['stddev_seconds'] = 0.0;
                $entry['loss_ratio'] = 0.0;
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
