<?php
/**
 *    Copyright (C) 2017 Fabian Franz
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

namespace OPNsense\Rspamd\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Rspamd\RSpamd;

class ServiceController extends ApiControllerBase
{

    /**
     * restart rspamd service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('rspamd restart');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }

    /**
     * retrieve status of rspamd
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $rspamd = new RSpamd();
        $response = $backend->configdRun('rspamd status');

        if (strpos($response, 'not running') > 0) {
            if ((string)$rspamd->general->enabled == 1) {
                $status = 'stopped';
            } else {
                $status = 'disabled';
            }
        } elseif (strpos($response, 'is running') > 0) {
            $status = 'running';
        } elseif ((string)$rspamd->general->enabled == 0) {
            $status = 'disabled';
        } else {
            $status = 'unknown';
        }


        return array('status' => $status);
    }

    /**
     * reconfigure rspamd, generate config and reload
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $rspamd = new RSpamd();
            $backend = new Backend();

            $this->stopAction();

            // generate template
            $backend->configdRun('template reload OPNsense/Rspamd');

            // (re)start daemon
            if ((string)$rspamd->general->enabled == '1') {
                $this->startAction();
            }

            return array('status' => 'ok');
        } else {
            return array('status' => 'failed');
        }
    }

    /**
     * stop rspamd service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('rspamd stop');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }
    /**
     * start rspamd service
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun('rspamd start');
            return array('response' => $response);
        } else {
            return array('response' => array());
        }
    }
}
