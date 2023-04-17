<?php
/**
 *    Copyright (C) 2023 Cloudfence - Julio Camargo
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

namespace OPNsense\WazuhAgent\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use \OPNsense\WazuhAgent\WazuhAgent;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\WazuhAgent\General';
    protected static $internalServiceTemplate = 'OPNsense/WazuhAgent';
    protected static $internalServiceEnabled = 'enabled';
    protected static $internalServiceName = 'wazuhagent';


    /**
     * start wazuh agent service
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("wazuhagent start");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * stop wazuh agent service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("wazuhagent stop");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * retrieve status of wazuh agent service
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("wazuhagent status");

        if (strpos($response, "not running") > 0) {
            $status = "stopped";
        } elseif (strpos($response, "is running") > 0) {
            $status = "running";
        } else {
            $status = "unkown";
        }

        return array("status" => $status);
    }
    
    /**
     * register agent with Wazuh manager
     * @return array
     * @throws \Exception
     */
    public function registerAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("wazuhagent register");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }
    /**
     * retrieve registration status of wazuh agent service
     * @return array
     * @throws \Exception
     */
    public function checkAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("wazuhagent register-check");
        

        if (strpos($response, "pending") > 0) {
            $status = "pending";
        } elseif (strpos($response, "connected") > 0) {
            $status = "registered";
        } elseif (strpos($response, "disconnected") > 0) {
            $status = "registered";
        } else {
            $status = "pending";
        }

        return array("register" => $status);
    }

    /**
     * reconfigure wazuh agent, generate config and reload
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            $backend = new Backend();
            $model = $this->getModel();

            $runStatus = $this->statusAction();
            // stop wazuh agent when disabled
            if ($runStatus['status'] == "running" &&
                ($model->enabled->__toString() == "0")) {
                $this->stopAction();
            }
            // reload template
            $backend->configdRun("template reload OPNsense/WazuhAgent");
            // reload syslog-ng
            $backend->configdRun("syslog reload");
            

            // (res)start daemon
            if ($model->enabled->__toString() == "1") {
                if ($runStatus['status'] == "running") {
                    $backend->configdRun("wazuhagent restart");
                } else {
                    $this->startAction();
                }
            }
            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }
}
