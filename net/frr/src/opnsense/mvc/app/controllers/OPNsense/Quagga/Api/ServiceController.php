<?php

/*
 * Copyright (C) 2015-2017 Deciso B.V.
 * Copyright (C) 2017 Fabian Franz
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

namespace OPNsense\Quagga\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Quagga\General;

/**
 * Class ServiceController
 * @package OPNsense\Quagga
 */
class ServiceController extends ApiControllerBase
{
    /**
     * start quagga service and reload filter rules to pass OSPF
     * before the bogon filter kills the routing protocol packets
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('quagga start');
            $backend->configdRun('filter reload');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }

    /**
     * stop quagga service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('quagga stop');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }

    /**
     * restart quagga service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('quagga restart');
            $backend->configdRun('filter reload');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }

    /**
     * retrieve status of quagga
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $mdlGeneral = new General();
        $response = $backend->configdRun('quagga status');

        if (strpos($response, 'not running') > 0) {
            if ($mdlGeneral->enabled->__toString() == 1) {
                $status = 'stopped';
            } else {
                $status = 'disabled';
            }
        } elseif (strpos($response, 'is running') > 0) {
            $status = 'running';
        } elseif ($mdlGeneral->enabled->__toString() == 0) {
            $status = 'disabled';
        } else {
            $status = 'unknown';
        }


        return array('status' => $status);
    }

    /**
     * reconfigure quagga, generate config and reload
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $mdlGeneral = new General();
            $backend = new Backend();

            $runStatus = $this->statusAction();

            // stop quagga if it is running or not
            $this->stopAction();

            // generate template
            $backend->configdRun('template reload OPNsense/Quagga');

            // (res)start daemon
            if ($mdlGeneral->enabled->__toString() == 1) {
                $this->startAction();
            }

            return array('status' => 'ok');
        } else {
            return array('status' => 'failed');
        }
    }
}
