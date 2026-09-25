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

namespace OPNsense\AutoRollback\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

/**
 * Service API controller - handles safe mode operations and status.
 *
 * API Endpoints:
 *   POST /api/autorollback/service/start         - Enter safe mode
 *   POST /api/autorollback/service/confirm        - Confirm changes
 *   POST /api/autorollback/service/cancel         - Cancel and rollback
 *   POST /api/autorollback/service/extend         - Extend timer
 *   GET  /api/autorollback/service/status         - Get current status
 */
class ServiceController extends ApiControllerBase
{
    /**
     * Start safe mode - snapshot current config and begin countdown.
     *
     * @return array result
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();

            // Optional custom timeout from POST body
            $timeout = $this->request->getPost('timeout', 'int', null);
            $param = $timeout ? (string)$timeout : '';

            $response = $backend->configdpRun('autorollback safemode.start', [$param]);
            $result = json_decode(trim($response), true);

            if ($result === null) {
                return ['status' => 'error', 'message' => 'Backend returned invalid response'];
            }

            return $result;
        }
        return ['status' => 'error', 'message' => 'POST required'];
    }

    /**
     * Confirm safe mode changes - accept the configuration.
     *
     * @return array result
     */
    public function confirmAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('autorollback safemode.confirm');
            $result = json_decode(trim($response), true);

            if ($result === null) {
                return ['status' => 'error', 'message' => 'Backend returned invalid response'];
            }

            return $result;
        }
        return ['status' => 'error', 'message' => 'POST required'];
    }

    /**
     * Cancel safe mode - rollback to previous config immediately.
     *
     * @return array result
     */
    public function cancelAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('autorollback safemode.cancel');
            $result = json_decode(trim($response), true);

            if ($result === null) {
                return ['status' => 'error', 'message' => 'Backend returned invalid response'];
            }

            return $result;
        }
        return ['status' => 'error', 'message' => 'POST required'];
    }

    /**
     * Extend the safe mode countdown timer.
     *
     * @return array result
     */
    public function extendAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();

            $seconds = $this->request->getPost('seconds', 'int', 60);
            $response = $backend->configdpRun('autorollback safemode.extend', [(string)$seconds]);
            $result = json_decode(trim($response), true);

            if ($result === null) {
                return ['status' => 'error', 'message' => 'Backend returned invalid response'];
            }

            return $result;
        }
        return ['status' => 'error', 'message' => 'POST required'];
    }

    /**
     * Get current auto-rollback status.
     *
     * @return array status information
     */
    public function statusAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('autorollback status');
        $result = json_decode(trim($response), true);

        if ($result === null) {
            return [
                'status' => 'error',
                'message' => 'Backend returned invalid response',
                'system_state' => 'unknown',
            ];
        }

        return $result;
    }
}
