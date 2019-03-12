<?php

/**
 *    Copyright (C) 2018 Alec Samuel Armbruster <alecsamuelarmbruster@gmail.com>
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

namespace OPNsense\Unboundbl\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Unboundbl\General;

class ServiceController extends ApiMutableServiceControllerBase
{
    static protected $internalServiceClass = '\OPNsense\Unboundbl\General';
    static protected $internalServiceTemplate = 'OPNsense/Unboundbl';
    static protected $internalServiceEnabled = 'enabled';
    static protected $internalServiceName = 'unboundbl';
    
    public function refreshAction()
    {
        $this->sessionClose();
        $backend = new Backend();
        $response = $backend->configdpRun('unboundbl refresh');
        return array("message" => $response);
    }
    public function reloadAction()
    {
        $this->sessionClose();
        $backend = new Backend();
        $backend->configdRun("template reload OPNsense/Unboundbl");
        return;
    }
        public function statsAction()
    {
        $this->sessionClose();
        $backend = new Backend();
        $response = $backend->configdpRun('unboundbl stats');
        return array("message" => $response);
    }
}
