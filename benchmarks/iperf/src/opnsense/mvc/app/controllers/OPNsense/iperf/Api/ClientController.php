<?php

/*
 * Copyright (C) 2026 Cedrik Pischem
 * Copyright (C) 2026 François Maymil
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
use OPNsense\Core\Config;

class ClientController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'client';
    protected static $internalModelClass = 'OPNsense\iperf\Client';

    /**
     * Validate the settings and start an iperf client job.
     */
    public function setAction()
    {
        $result = parent::setAction();
        if ($result['result'] === 'failed') {
            return $result;
        }

        $nodes = $this->getModel()->settings->getNodes();
        foreach ($nodes as $key => $value) {
            if (is_array($value)) {
                $items = [];
                foreach ($value as $itemkey => $itemval) {
                    if (!empty($itemval['selected'])) {
                        $items[] = $itemkey;
                    }
                }
                $nodes[$key] = implode(',', $items);
            }
        }
        if (!empty($nodes['interface'])) {
            /* the script binds to an address, it needs the device name */
            $nodes['interface'] = (string)Config::getInstance()->object()->interfaces->{$nodes['interface']}->if;
        }
        $payload = json_decode((new Backend())->configdpRun(
            'iperf client create',
            [base64_encode(json_encode($nodes))]
        ), true);
        return !empty($payload) ? $payload : ['status' => 'failed'];
    }

    /**
     * start client job
     */
    public function startAction($jobid)
    {
        return $this->runJobAction($jobid, 'start');
    }

    /**
     * stop client job
     */
    public function stopAction($jobid)
    {
        return $this->runJobAction($jobid, 'stop');
    }

    private function runJobAction($jobid, $action)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed'];
        }
        $payload = json_decode((new Backend())->configdpRun("iperf client $action", [$jobid]), true);
        return !empty($payload) ? $payload : ['status' => 'failed'];
    }

    /**
     * remove client job and its result
     */
    public function removeAction($jobid)
    {
        return $this->runJobAction($jobid, 'remove');
    }

    /**
     * view the latest client result
     */
    public function viewAction($jobid)
    {
        $payload = json_decode((new Backend())->configdpRun('iperf client view', [$jobid]), true);
        return !empty($payload) ? $payload : ['status' => 'failed'];
    }

    /**
     * search client jobs and their results
     */
    public function searchJobsAction()
    {
        $data = json_decode((new Backend())->configdRun('iperf client list'), true);
        $records = (!empty($data) && !empty($data['jobs'])) ? $data['jobs'] : [];
        return $this->searchRecordsetBase($records);
    }
}
