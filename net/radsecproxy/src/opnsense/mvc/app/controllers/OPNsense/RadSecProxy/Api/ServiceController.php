<?php

/*
    Copyright (C) 2020 Tobias Boehnert
    All rights reserved.
    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:
    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.
    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

namespace OPNsense\RadSecProxy\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\RadSecProxy\RadSecProxy';
    protected static $internalServiceTemplate = 'OPNsense/RadSecProxy';
    protected static $internalServiceEnabled = 'general.Enabled';
    protected static $internalServiceName = 'radsecproxy';
    protected function reconfigureForceRestart()
    {
        return 0;
    }

    public function checkconfigAction()
    {
        $backend = new Backend();
        // first generate template based on current configuration
        $backend->configdRun('template reload OPNsense/RadSecProxy');
        
        // now export all the required files (or syntax check will fail)
        $backend->configdRun("radsecproxy setup");

        // finally run the syntax check
        $response = $backend->configdRun("radsecproxy checkconfig");
        return array("result" => trim($response));
    }
}
