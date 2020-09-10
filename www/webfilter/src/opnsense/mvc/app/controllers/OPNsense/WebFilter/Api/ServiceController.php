<?php

/**
 *    
 *    Copyright (C) 2018-2020 Cloudfence
 *    Copyright (c) 2019 Deciso B.V.
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

namespace OPNsense\WebFilter\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use \OPNsense\Core\Backend;
use OPNsense\WebFilter\WebFilter;


/**
 * Class ServiceController
 * @package OPNsense\WebFilter
 */
class ServiceController extends ApiMutableServiceControllerBase
{

    protected static $internalServiceClass = '\OPNsense\WebFilter\WebFilter';
    protected static $internalServiceTemplate = 'OPNsense/WebFilter';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceName = 'webfilter';

    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            $this->getModel()->configClean();
            $model = $this->getModel();
            $backend = new Backend();
            $backend->configdRun("template reload OPNsense/WebFilter");
            $backend->configdRun("webfilter reconfigure");
            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }

    public function downloadAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $command = 'webfilter updatewfdb';
            if ($this->request->hasPost('action')) {
                $command .= ' download';
            }
            $response = trim($backend->configdRun($command));
            return array('status' => $response);
        } else {
            return array('status' => 'error');
        }
    }
   
}
