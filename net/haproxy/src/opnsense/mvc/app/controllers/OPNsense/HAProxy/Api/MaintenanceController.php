<?php

/**
 *    Copyright (C) 2021 Andreas Stuerz
 *    Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\HAProxy\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\HAProxy\HAProxy;

/**
 * Class MaintenanceController
 * @package OPNsense\HAProxy
 */
class MaintenanceController extends ApiControllerBase
{
    /**
     * jQuery bootstrap server list
     * @return array|mixed
     */
    public function searchServerAction()
    {
        #print_r($this->request->getPost());
        #print_r($this->request->getPost('sort'));

        return $this->getData(
            ["server_status_list"],
            ["rowCount", "current", "searchPhrase", "sort"]
        );
    }

    /**
     * set server weight
     * @return array|mixed
     */
    public function serverWeightAction()
    {
        return $this->saveData(
            ["server_weight"],
            ["backend", "server", "weight"]
        );
    }

    /**
     * set server administrative state
     * @return array|mixed
     */
    public function serverStateAction()
    {
        return $this->saveData(
            ["server_state"],
            ["backend", "server", "state"]
        );
    }

    /**
     * Execute a backend command securely
     * @param array $command
     * @param array $arguments
     * @return string
     */
    protected function safeBackendCmd(array $command, array $arguments = [])
    {
        $backend = new Backend();

        foreach ($arguments as $name) {
            $val = $this->request->getPost($name);
            if (is_array($val) and $name == 'sort') {
                $sort =  key(array_slice($val, 0, 1));
                $sort_dir = $val[$sort];
                $command[] = $sort;
                $command[] = $sort_dir;
                continue;
            }
            $command[] = $val;
        }

        $command = array_map(function ($value) {
            return escapeshellarg(empty($value = trim($value)) ? null : $value);
        }, $command);

        return trim($backend->configdRun("haproxy " . join(" ", $command)));
    }

    /**
     * Executes a backend command to get data
     * @param array $command
     * @param array $arguments
     * @return string|string[]
     */
    protected function getData(array $command, array $arguments = [])
    {
        if ($this->request->isPost()) {
            return $this->safeBackendCmd($command, $arguments);
        }
        return ["status" => "unavailable"];
    }

    /**
     * Executes a backend command to save data
     * @param array $command
     * @param array $arguments
     * @return array|string[]
     */
    protected function saveData(array $command, array $arguments = [])
    {
        if ($this->request->isPost()) {
            if ($error = $this->safeBackendCmd($command, $arguments)) {
                return [
                    "status" => "error",
                    "message" => $error
                ];
            } else {
                return ["status" => "ok"];
            }
        }
        return [
            "status" => 'unavailable',
            "message" => 'only accept POST Requests.'
        ];
    }
}
