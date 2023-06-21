<?php

/**
 *    Copyright (C) 2017 Frank Wall
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

namespace OPNsense\AcmeClient\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Cron\Cron;
use OPNsense\AcmeClient\AcmeClient;

/**
 * Class ServiceController
 * @package OPNsense\AcmeClient
 */
class ServiceController extends ApiControllerBase
{
    /**
     * start acmeclient service (in background)
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("acmeclient http-start");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * stop acmeclient service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("acmeclient http-stop");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * restart acme_http_challenge service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("acmeclient http-restart");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * retrieve status of acme_http_challenge service
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $model = new AcmeClient();
        $response = $backend->configdRun("acmeclient http-status");

        if (strpos($response, "not running") > 0) {
            if ($model->settings->enabled->__toString() == 1) {
                $status = "stopped";
            } else {
                $status = "disabled";
            }
        } elseif (strpos($response, "is running") > 0) {
            $status = "running";
        } elseif ($model->settings->enabled->__toString() == 0) {
            $status = "disabled";
        } else {
            $status = "unkown";
        }

        return array("status" => $status);
    }

    /**
     * reconfigure acmeclient, generate config and reload
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $force_restart = false;

            $mdlAcme = new AcmeClient();
            $backend = new Backend();
            $runStatus = $this->statusAction();

            // stop acmeclient when disabled
            if (
                $runStatus['status'] == "running" &&
                ($mdlAcme->settings->enabled->__toString() == 0 ||
                $force_restart)
            ) {
                $this->stopAction();
            }

            // generate template
            $backend->configdRun('template reload OPNsense/AcmeClient');

            // (res)start daemon
            if ($mdlAcme->settings->enabled->__toString() == 1) {
                if ($runStatus['status'] == "running" && !$force_restart) {
                    $backend->configdRun("acmeclient http-restart");
                } else {
                    $this->startAction();
                }
            }

            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * run syntax check for our custom lighttpd configuration
     * @return array
     * @throws \Exception
     */
    public function configtestAction()
    {
        $backend = new Backend();
        // first generate template based on current configuration
        $backend->configdRun('template reload OPNsense/AcmeClient');
        // finally run the syntax check
        $response = $backend->configdRun("acmeclient configtest");
        return array("result" => $response);
    }

    /**
     * Run sign or renew (if required) command for ALL certificates
     * @return array
     * @throws \Exception
     */
    public function signallcertsAction()
    {
        $backend = new Backend();
        // run the command
        $response = $backend->configdRun("acmeclient sign-all-certs");
        return array("result" => $response);
    }

    /**
     * Remove ALL certificate data and configuration and reset ALL states
     * @return array
     * @throws \Exception
     */
    public function resetAction()
    {
        $model = new AcmeClient();
        // reset certificate states
        foreach ($model->getNodeByReference('certificates.certificate')->iterateItems() as $cert) {
            $cert->lastUpdate = null;
            $cert->statusCode = null;
            $cert->statusLastUpdate = null;
        }
        // reset account states
        foreach ($model->getNodeByReference('accounts.account')->iterateItems() as $account) {
            $account->statusLastUpdate = null;
        }
        // reset acme.sh data
        $backend = new Backend();
        $response = $backend->configdRun("acmeclient reset-acme-client");
        // serialize to config and save
        $model->serializeToConfig();
        Config::getInstance()->save();
        return array("result" => $response);
    }
}
