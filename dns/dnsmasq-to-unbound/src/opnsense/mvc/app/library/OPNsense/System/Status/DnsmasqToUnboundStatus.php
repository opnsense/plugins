<?php

/*
 * Copyright (C) 2025 C. Hall
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

namespace OPNsense\System\Status;

use OPNsense\System\AbstractStatus;
use OPNsense\System\SystemStatusCode;

/**
 * Status provider for Dnsmasq to Unbound DNS registration service.
 * Reads status from JSON file written by the Python watcher daemon.
 */
class DnsmasqToUnboundStatus extends AbstractStatus
{
    private const STATUS_FILE = '/var/run/dnsmasq_watcher_status.json';

    public function __construct()
    {
        $this->internalPriority = 5;
        $this->internalPersistent = false;
        $this->internalTitle = gettext('Dnsmasq to Unbound');
        $this->internalLocation = '/ui/dnsmasqtounbound/settings';
    }

    public function collectStatus()
    {
        if (!file_exists(self::STATUS_FILE)) {
            // No status file means service is not running or disabled
            return;
        }

        $content = @file_get_contents(self::STATUS_FILE);
        if ($content === false) {
            return;
        }

        $status = @json_decode($content, true);
        if (!is_array($status) || !isset($status['level'])) {
            return;
        }

        // Map Python StatusLevel values to OPNsense SystemStatusCode
        // Python: OK=2, NOTICE=1, WARNING=0, ERROR=-1
        // PHP: OK=2, NOTICE=1, WARNING=0, ERROR=-1
        switch ($status['level']) {
            case -1:
                $this->internalStatus = SystemStatusCode::ERROR;
                break;
            case 0:
                $this->internalStatus = SystemStatusCode::WARNING;
                break;
            case 1:
                $this->internalStatus = SystemStatusCode::NOTICE;
                break;
            default:
                // OK or unknown - don't set status (no notification)
                return;
        }

        $this->internalMessage = $status['message'] ?? gettext('Check system log for details.');
        $this->internalTimestamp = $status['timestamp'] ?? time();
    }
}
