<?php

/*
    Copyright (C) 2018 Smart-Soft

    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
    notice, this list of conditions and the following disclaimer in the
    documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

namespace OPNsense\Smart\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class ServiceController extends ApiControllerBase
{
    private function getDevices()
    {
        $backend = new Backend();

        $devices = preg_split("/[\s]+/", trim($backend->configdRun("smart list")));

        return $devices;
    }

    public function listAction()
    {
        if ($this->request->isPost()) {
            return array("devices" => $this->getDevices());
        }

        return array("message" => "Unable to run list action");
    }

    public function infoAction()
    {
        if ($this->request->isPost()) {
            $device = $this->request->getPost('device');
            $type   = $this->request->getPost('type');

            if (!in_array($device, $this->getDevices())) {
                return array("message" => "Invalid device name");
            }

            $valid_info_types = array("i", "H", "c", "A", "a");

            if (!in_array($type, $valid_info_types)) {
                return array("message" => "Invalid info type");
            }

            $backend = new Backend();

            $output = $backend->configdpRun("smart", array("info", $type, "/dev/" . $device));

            return array("output" => $output);
        }

        return array("message" => "Unable to run info action");
    }

    public function logsAction()
    {
        if ($this->request->isPost()) {
            $device = $this->request->getPost('device');
            $type   = $this->request->getPost('type');

            if (!in_array($device, $this->getDevices())) {
                return array("message" => "Invalid device name");
            }

            $valid_log_types = array("error", "selftest");

            if (!in_array($type, $valid_log_types)) {
                return array("message" => "Invalid log type");
            }

            $backend = new Backend();

            $output = $backend->configdpRun("smart", array("log", $type, "/dev/" . $device));

            return array("output" => $output);
        }

        return array("message" => "Unable to run logs action");
    }

    public function testAction()
    {
        if ($this->request->isPost()) {
            $device = $this->request->getPost('device');
            $type   = $this->request->getPost('type');

            if (!in_array($device, $this->getDevices())) {
                return array("message" => "Invalid device name");
            }

            $valid_test_types = array("offline", "short", "long", "conveyance");

            if (!in_array($type, $valid_test_types)) {
                return array("message" => "Invalid test type");
            }

            $backend = new Backend();

            $output = $backend->configdpRun("smart", array("test", $type, "/dev/" . $device));

            return array("output" => $output);
        }

        return array("message" => "Unable to run test action");
    }

    public function abortAction()
    {
        if ($this->request->isPost()) {
            $device = $this->request->getPost('device');

            if (!in_array($device, $this->getDevices())) {
                return array("message" => "Invalid device name");
            }

            $backend = new Backend();

            $output = $backend->configdpRun("smart", array("abort", "/dev/" . $device));

            return array("output" => $output);
        }

        return array("message" => "Unable to run abort action");
    }
}
