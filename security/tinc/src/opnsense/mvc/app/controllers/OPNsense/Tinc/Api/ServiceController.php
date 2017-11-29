<?php
/**
 *    Copyright (C) 2016 Deciso B.V.
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
namespace OPNsense\Tinc\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

/**
 * Class ServiceController
 * @package OPNsense\Tinc
 */
class ServiceController extends ApiControllerBase
{
    /**
     * reconfigure captive portal
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $backend->configdRun('template reload OPNsense/Tinc');
            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * start tinc service (in background)
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("tinc start");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * stop tinc service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("tinc stop");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * restart tinc service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdRun("tinc restart");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }
}
