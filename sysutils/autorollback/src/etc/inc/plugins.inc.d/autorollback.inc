<?php

/**
 *    Copyright (C) 2026 MP Lindsey
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
 */

/**
 * Register cron jobs for the auto-rollback watchdog.
 * The watchdog runs every minute to:
 *   1. Check if safe mode timer expired (cron safety net)
 *   2. Run connectivity health checks (if watchdog is enabled)
 *
 * @return array cron job definitions
 */
function autorollback_cron()
{
    return [
        [
            'autocron' => [
                '/usr/local/sbin/configctl autorollback watchdog.check',
                '*/1',  // Every minute
            ],
        ],
    ];
}

/**
 * Register the auto-rollback service for the service manager.
 * This allows starting/stopping/status via the Services page and API.
 *
 * @return array service definitions
 */
function autorollback_services()
{
    $mdl = new \OPNsense\AutoRollback\AutoRollback();

    $services = [];

    if ((string)$mdl->general->Enabled == '1') {
        $services[] = [
            'description' => gettext('Auto Rollback Safe Mode'),
            'configd' => [
                'restart' => ['autorollback safemode.start'],
                'start'   => ['autorollback safemode.start'],
                'stop'    => ['autorollback safemode.cancel'],
            ],
            'name' => 'autorollback',
            'nocheck' => true,  // No PID file to check — uses state files
        ];
    }

    return $services;
}

/**
 * Register syslog facility for auto-rollback events.
 *
 * @return array syslog configuration
 */
function autorollback_syslog()
{
    return [
        'autorollback' => [
            'facility' => ['autorollback', 'autorollback-recovery'],
        ],
    ];
}
