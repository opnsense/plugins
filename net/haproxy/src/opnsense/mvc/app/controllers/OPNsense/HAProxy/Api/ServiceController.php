<?php
/**
 *    Copyright (C) 2016 Frank Wall
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

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\HAProxy\HAProxy;

/**
 * Class ServiceController
 * @package OPNsense\HAProxy
 */
class ServiceController extends ApiControllerBase
{
    /**
     * start haproxy service (in background)
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("haproxy start");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * stop haproxy service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("haproxy stop");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * restart haproxy service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("haproxy restart");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * retrieve status of haproxy service
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $mdlProxy = new HAProxy();
        $response = $backend->configdRun("haproxy status");

        if (strpos($response, "not running") > 0) {
            if ($mdlProxy->general->enabled->__toString() == 1) {
                $status = "stopped";
            } else {
                $status = "disabled";
            }
        } elseif (strpos($response, "is running") > 0) {
            $status = "running";
        } elseif ($mdlProxy->general->enabled->__toString() == 0) {
            $status = "disabled";
        } else {
            $status = "unkown";
        }

        return array("status" => $status);
    }

    /**
     * reconfigure haproxy, generate config and reload
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $force_restart = false;
            // close session for long running action
            $this->sessionClose();

            $mdlProxy = new HAProxy();
            $backend = new Backend();

            $runStatus = $this->statusAction();

            // stop haproxy when disabled
            if ($runStatus['status'] == "running" &&
               ($mdlProxy->general->enabled->__toString() == 0 || $force_restart)) {
                $this->stopAction();
            }

            // generate template
            $backend->configdRun('template reload OPNsense/HAProxy');

            // (res)start daemon
            if ($mdlProxy->general->enabled->__toString() == 1) {
                if ($runStatus['status'] == "running" && !$force_restart) {
                    $backend->configdRun("haproxy reload");
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
     * run syntax check for haproxy configuration
     * @return array
     * @throws \Exception
     */
    public function configtestAction()
    {
        $backend = new Backend();
        // first generate template based on current configuration
        $backend->configdRun('template reload OPNsense/HAProxy');
        // now export all the required files (or syntax check will fail)
        $backend->configdRun("haproxy setup");
        // finally run the syntax check
        $response = $backend->configdRun("haproxy configtest");
        return array("result" => $response);
    }
}
