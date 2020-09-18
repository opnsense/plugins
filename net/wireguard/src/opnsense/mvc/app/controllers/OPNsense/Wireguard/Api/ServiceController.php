<?php

/**
 *    Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Wireguard\Api;

require_once('config.inc');
use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Wireguard\General;

/**
 * Class ServiceController
 * @package OPNsense\Wireguard
 */
class ServiceController extends ApiControllerBase
{
    /**
     * start wireguard service
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('wireguard start');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }

    /**
     * stop wireguard service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('wireguard stop');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }

    /**
     * restart wireguard service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('wireguard restart');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }

    /**
     * show wireguard config
     * @return array
     */
    public function showconfAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("wireguard showconf");
        return array("response" => $response);
    }

    /**
     * show wireguard handshakes
     * @return array
     */
    public function showhandshakeAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("wireguard showhandshake");
        return array("response" => $response);
    }

    /**
     * retrieves status of wireguard
     * @return array
     */
    public function statusAction()
    {
        $backend = new Backend();
        $general = new General();
        $response = $backend->configdRun("wireguard showconf");

        if (strpos($response, "interface:") !== false) {
            $status = "running";
        } else {
            if ($general->enabled->__toString() == 0) {
                $status = "disabled";
            } else {
                $status = "stopped";
            }
        }

        return array("status" => $status);
    }

    /**
     * Start/stop/reload Wireguard
     * @return array
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();

            $general = new General();
            $backend = new Backend();

            if ($general->enabled->__toString() != 1) {
                $backend->configdRun("wireguard stop");
            }

            $backend->configdRun('template reload OPNsense/Wireguard');

            if ($general->enabled->__toString() == 1) {
                $runStatus = $this->statusAction();
                if (!$this->hasServerChange($backend) && $runStatus['status'] == 'running') {
                    $backend->configdRun("wireguard reload");
                } else {
                    $backend->configdRun("wireguard restart");
                }
            }
            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }

    private function hasServerChange($backend)
    {
        return $this->countConfiguredServers() != $this->runningServers($backend);
    }

    private function countConfiguredServers()
    {
        $config = Config::getInstance()->toArray();
        if (!isset($config['OPNsense']['wireguard']['server']['servers'])) {
            return 0;
        }
        return count($config['OPNsense']['wireguard']['server']['servers']['server']);
    }

    private function runningServers($backend)
    {
        $showconf = $backend->configdRun("wireguard showconf");
        return substr_count($showconf, 'interface:');
    }
}
