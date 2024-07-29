<?php

/**
 *    Copyright (C) 2016 EURO-LOG AG
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

namespace OPNsense\FtpProxy\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\FtpProxy\FtpProxy;

/**
 * Class ServiceController
 * @package OPNsense\FtpProxy
 */
class ServiceController extends ApiControllerBase
{
    public function statusAction($uuid)
    {
        $result = array("result" => "failed", "function" => "status");
        if ($uuid != null) {
            $mdlFtpProxy = new FtpProxy();
            $node = $mdlFtpProxy->getNodeByReference('ftpproxy.' . $uuid);
            if ($node != null) {
                $result['result'] = $this->callBackend('status', $node);
            }
        }
        return $result;
    }

    /**
     * start a ftp-proxy process
     * @param $uuid item unique id
     * @return array
     */
    public function startAction($uuid)
    {
        $result = array("result" => "failed", "function" => "start");
        if ($uuid != null) {
            $mdlFtpProxy = new FtpProxy();
            $node = $mdlFtpProxy->getNodeByReference('ftpproxy.' . $uuid);
            if ($node != null) {
                $result['result'] = $this->callBackend('start', $node);
            }
        }
        return $result;
    }

    /**
     * stop a ftp-proxy process
     * @param $uuid item unique id
     * @return array
     */
    public function stopAction($uuid)
    {
        $result = array("result" => "failed", "function" => "stop");
        if ($uuid != null) {
            $mdlFtpProxy = new FtpProxy();
            $node = $mdlFtpProxy->getNodeByReference('ftpproxy.' . $uuid);
            if ($node != null) {
                $result['result'] = $this->callBackend('stop', $node);
            }
        }
        return $result;
    }

    /**
     * restart a ftp-proxy process
     * @param $uuid item unique id
     * @return array
     */
    public function restartAction($uuid)
    {
        if ($uuid != null) {
            $mdlFtpProxy = new FtpProxy();
            $node = $mdlFtpProxy->getNodeByReference('ftpproxy.' . $uuid);
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
            $instance = preg_replace("/\./", "_", $node->listenaddress->__toString()) . "_" . $node->listenport->__toString();
            return trim($backend->configdpRun('ftpproxy', array($action, $instance)));
        }
        if ($action == 'template') {
            return trim($backend->configdRun('template reload OPNsense/FtpProxy'));
        }
        if ($action == 'reload') {
            $ret = trim($backend->configdRun('ftpproxy reload'));
            /* also requires anchors in rules: */
            $backend->configdRun('filter reload');
            return $ret;
        }
        return "Wrong action defined";
    }
}
