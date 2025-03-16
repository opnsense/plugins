<?php

/**
 *    Copyright (C) 2024 Volodymyr Paprotski
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

namespace OPNsense\StepCA\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

/**
 * Class ServiceController
 * @package OPNsense\StepCA
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\StepCA\StepCA';
    protected static $internalServiceTemplate = 'OPNsense/StepCA';
    protected static $internalServiceEnabled = 'Enabled';
    protected static $internalServiceName = 'stepca';

    // protected function reconfigureForceRestart()
    // {
    //     return 1;
    // }

    public function initcaAction()
    {
        if ($this->request->isPost()) {
            $bckresult = json_decode(trim((new Backend())->configdRun("stepca initca")), true);
            if ($bckresult !== null) {
                // only return valid json type responses
                return $bckresult;
            }
        }
        return ["message" => "unable to run config action"];
    }

    // Copy of original reconfigure,
    // added failure detection
    public function reconfigureAction()
    {
        if (true || $this->request->isPost()) {
            $this->sessionClose();

            $backend = new Backend();

            if (!$this->serviceEnabled() || $this->reconfigureForceRestart()) {
                $backend->configdRun(escapeshellarg(static::$internalServiceName) . ' stop');
            }

            if ($this->invokeInterfaceRegistration()) {
                $backend->configdRun('interface invoke registration');
            }

            if (!empty(static::$internalServiceTemplate)) {
                $result = trim($backend->configdpRun('template reload', [static::$internalServiceTemplate]) ?? '');
                if ($result !== 'OK') {
                    throw new UserException(sprintf(
                        gettext('Template generation failed for internal service "%s". See backend log for details.'),
                        static::$internalServiceName
                    ), gettext('Configuration exception'));
                }
            }

            $status = 'ok';
            if ($this->serviceEnabled()) {
                $runStatus = $this->statusAction();
                if ($runStatus['status'] != 'running') {
                    $response = $backend->configdRun(escapeshellarg(static::$internalServiceName) . ' start');
                } else {
                    $response = $backend->configdRun(escapeshellarg(static::$internalServiceName) . ' reload');
                }
                if (trim($response) !== 'OK') {
                    $status = 'failed';
                }
            }

            return array('status' => $status);
        } else {
            return array('status' => 'failed');
        }
    }
}
