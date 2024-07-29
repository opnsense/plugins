<?php

/*
 *    Copyright (C) 2020 Martin Wasley
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

namespace OPNsense\UDPBroadcastRelay\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\UDPBroadcastRelay\UDPBroadcastRelay;
use OPNsense\Core\Backend;

/**
 * Class ServiceController Handles settings related API actions for the UDPBroadcastRelay
 * @package OPNsense\UDPBroadcastRelay
 */
class ServiceController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'udpbroadcastrelay';
    protected static $internalModelClass = '\OPNsense\UDPBroadcastRelay\UDPBroadcastRelay';
    protected static $internalModelUseSafeDelete = true;

    public function statusAction($uuid)
    {
        $result = array("result" => "failed", "function" => "status");
        if ($uuid != null) {
            $mdlUDPBroadcastRelay = new UDPBroadcastRelay();
            $node = $mdlUDPBroadcastRelay->getNodeByReference('udpbroadcastrelay.' . $uuid);
            if ($node != null) {
                $result['result'] = $this->callBackend('status', $node);
            }
        }
        return $result;
    }

    /**
     * start a udpbroadcastrelay process
     * @param $uuid item unique id
     * @return array
     */
    public function startAction($uuid)
    {
        $result = array("result" => "failed", "function" => "start");
        if ($uuid != null) {
            $mdlUDPBroadcastRelay = new UDPBroadcastRelay();
            $node = $mdlUDPBroadcastRelay->getNodeByReference('udpbroadcastrelay.' . $uuid);
            if ($node != null) {
                $result['result'] = $this->callBackend('start', $node);
            }
        }
        return $result;
    }

    /**
     * stop a udpbroadcastrelay process
     * @param $uuid item unique id
     * @return array
     */
    public function stopAction($uuid)
    {
        $result = array("result" => "failed", "function" => "stop");
        if ($uuid != null) {
            $mdlUDPBroadcastRelay = new UDPBroadcastRelay();
            $node = $mdlUDPBroadcastRelay->getNodeByReference('udpbroadcastrelay.' . $uuid);
            if ($node != null) {
                $result['result'] = $this->callBackend('stop', $node);
            }
        }
        return $result;
    }

    /**
     * restart a udpbroadcastrelay process
     * @param $uuid item unique id
     * @return array
     */
    public function restartAction($uuid)
    {
        if ($uuid != null) {
            $mdlUDPBroadcastRelay = new UDPBroadcastRelay();
            $node = $mdlUDPBroadcastRelay->getNodeByReference('udpbroadcastrelay.' . $uuid);
            if ($node != null) {
                $result['result'] = $this->callBackend('restart', $node);
            }
        }
        return $result;
    }

    /**
     * recreate configuration file from template
     * @return array
     */
    public function configAction()
    {
        $result = array("result" => "failed", "function" => "config");
        $result['result'] = $this->callBackend('template');
        return $result;
    }

    /**
     * reload configuration
     * @return array
     */
    public function reloadAction()
    {
        $result = $this->configAction();
        if ($result['result'] == 'OK') {
            $result['function'] = "reload";
            $result['result'] = $this->callBackend('reload');
        }
        return $result;
    }

    /**
     * call backend
     * @param action, node
     * @return string
     */
    protected function callBackend($action, &$node = null)
    {
        $backend = new Backend();
        if ($node != null) {
            $instance = preg_replace("/\./", "_", $node->InstanceID->__toString());
            return trim($backend->configdpRun('udpbroadcastrelay', array($action, $instance)));
        }
        if ($action == 'template') {
            return trim($backend->configdRun('template reload OPNsense/UDPBroadcastRelay'));
        }
        if ($action == 'reload') {
            return trim($backend->configdRun('udpbroadcastrelay reload'));
        }
        return "Wrong action defined";
    }
}
