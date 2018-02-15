<?php

/**
 *    Copyright (C) 2017-2018 EURO-LOG AG
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

namespace OPNsense\Monit\Api;

use \OPNsense\Base\ApiMutableServiceControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Monit\Monit;

/**
 * Class ServiceController
 * @package OPNsense\Monit
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    static protected $internalServiceClass = '\OPNsense\Monit\Monit';
    static protected $internalServiceEnabled = 'general.enabled';
    static protected $internalServiceTemplate = 'OPNsense/Monit';
    static protected $internalServiceName = 'monit';

    /**
     * test monit configuration
     * @return array
     */
    public function configtestAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();

            $backend = new Backend();

            $result['function'] = "configtest";
            $result['template'] = trim($backend->configdRun('template reload OPNsense/Monit'));
            if ($result['template'] != 'OK') {
                $result['result'] = "Template error: " . $result['template'];
                return $result;
            }
            $result['result'] = trim($backend->configdRun('monit configtest'));
            return $result;
        } else {
            return array('status' => 'failed');
        }
    }

     /**
      * avoid restarting Relayd on reconfigure
      */
    protected function reconfigureForceRestart()
    {
        return 0;
    }
}
