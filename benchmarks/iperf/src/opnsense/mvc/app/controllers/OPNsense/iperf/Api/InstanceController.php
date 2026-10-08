<?php

/*
 * Copyright (C) 2026 Cedrik Pischem
 * Copyright (C) 2017 Fabian Franz
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

namespace OPNsense\iperf\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class InstanceController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'instance';
    protected static $internalModelClass = 'OPNsense\iperf\Instance';

    /**
     * Validate the requested port and start an iperf server instance.
     */
    public function setAction()
    {
        $result = parent::setAction();
        if ($result['result'] === 'failed') {
            return $result;
        }

        $payload = json_decode((new Backend())->configdpRun(
            'iperf server create',
            [$this->getModel()->port->getValue()]
        ), true);
        return !empty($payload) ? $payload : ['status' => 'failed'];
    }

    public function searchJobsAction()
    {
        $payload = json_decode((new Backend())->configdRun('iperf server list'), true);
        $records = !empty($payload['jobs']) ? $payload['jobs'] : [];
        return $this->searchRecordsetBase($records);
    }

    public function startAction($jobId)
    {
        return $this->runJobAction($jobId, 'start');
    }

    public function stopAction($jobId)
    {
        return $this->runJobAction($jobId, 'stop');
    }

    private function runJobAction($jobId, $action)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed'];
        }
        $payload = json_decode((new Backend())->configdpRun("iperf server $action", [$jobId]), true);
        return !empty($payload) ? $payload : ['status' => 'failed'];
    }

    public function removeAction($jobId)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed'];
        }
        $payload = json_decode((new Backend())->configdpRun('iperf server remove', [$jobId]), true);
        return !empty($payload) ? $payload : ['status' => 'failed'];
    }
}
