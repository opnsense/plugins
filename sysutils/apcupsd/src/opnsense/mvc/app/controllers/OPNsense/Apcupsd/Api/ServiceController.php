<?php

/**
 *    Copyright (C) 2021 Dan Lundqvist
 *    Copyright (C) 2021 David Berry
 *    Copyright (C) 2021 Nicola Pellegrini
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Apcupsd\Api;

use DateTime;
use DateTimeInterface;
use OPNsense\Core\Backend;
use OPNsense\Base\ApiMutableServiceControllerBase;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Apcupsd\Apcupsd';
    protected static $internalServiceTemplate = 'OPNsense/Apcupsd';
    protected static $internalServiceEnabled = 'general.Enabled';
    protected static $internalServiceName = 'apcupsd';

    private static $dateTimeFields = [
        'DATE',
        'STARTTIME',
        'MASTERUPD',
        'END APC',
        'XONBATT',
        'XOFFBATT',
    ];

    private static $dateFields = [
        'MANDATE',
        'BATDATE'
    ];

    public function getUpsStatusAction()
    {
        $result = $this->getUpsStatusOutput();
        $result['status'] = null;
        if (!$result['error']) {
            $result['status'] = $this->parseUpsStatus($result['output']);
        }
        return $result;
    }

    private function parseUpsStatus($statusOutput)
    {
        $status = array();
        foreach (explode("\n", $statusOutput) as $line) {
            $kv = array_map('trim', explode(':', $line, 2));
            $key = $kv[0];
            $value = isset($kv[1]) ? $kv[1] : null;
            $norm = $value;
            if (empty($key)) {
                continue;
            }
            if ($value === 'N/A') {
                $norm = null;
            } elseif (in_array($key, self::$dateTimeFields, true)) {
                $norm = $this->tryParseDateTime($value);
            } elseif (in_array($key, self::$dateFields, true)) {
                $norm = $this->tryParseDate($value);
            } elseif (preg_match('/^((?:[0-9]*[.])?[0-9]+)(?:\s+\w+)?$/i', $value, $matches)) {
                $norm = floatval($matches[1]);
            }
            $status[$key] = array(
                'value' => $value,
                'norm' => $norm
            );
        }
        return $status;
    }

    private function tryParseDateTime($dateTimeString)
    {
        $formats = [
            'Y-m-d H:i:s P', // 2021-12-27 17:51:42 +0100
            'D M d H:i:s T Y' // Sat Sep 16 17:13:00 CEST 2000
        ];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $dateTimeString);
            if ($dt) {
                return $dt->format(DateTimeInterface::RFC3339);
            }
        }
        return $dateTimeString;
    }

    private function tryParseDate($dateString)
    {
        $formats = [
            'Y-m-d', // 2021-12-27
            'm/d/y', // 12/27/21
        ];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $dateString);
            if ($dt) {
                return $dt->format('Y-m-d');
            }
        }
        return $dateString;
    }

    private function getUpsStatusOutput()
    {
        $output = $error = null;

        if ($this->isEnabled()) {
            $backend = new Backend();
            $output = trim($backend->configdRun('apcupsd upsstatus'));
            if (empty($output)) {
                $error = 'Error: empty output from apcaccess';
            }
        } else {
            $error = 'Error: apcupsd is disabled';
        }

        return array(
            'error' => $error,
            'output' => $output
        );
    }

    private function isEnabled()
    {
        return $this->getModel()->general->Enabled == '1';
    }
}
