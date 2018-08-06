<?php

/*
    Copyright (c) 2018 Verb Networks Pty Ltd <contact@verbnetworks.com>
    Copyright (c) 2018 Nicholas de Jong <me@nicholasdejong.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
    ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace VerbNetworks\ConfigSync\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

class ServiceController extends ApiControllerBase
{
    
    public function reloadAction()
    {
        $response = array("status"=>"fail", "message" => "Invalid request");

        if ($this->request->isPost()) {
            $backend = new Backend();
            $backend_result = trim($backend->configdRun('template reload VerbNetworks/ConfigSync'));
            if (strtoupper($backend_result) == "OK") {
                $response = array("status"=>"success", "message" => "Template reload okay");
            }
        }
        
        return $response;
    }

    public function statusAction()
    {
        $response = array("status"=>"fail", "message" => "Invalid request");

        if ($this->request->isPost()) {
            $backend = new Backend();
            $backend_result = trim($backend->configdRun('configsync status'));
            if (false === strpos(strtolower($backend_result), ' not running')) {
                $response = array("status"=>"running");
            } else {
                $response = array("status"=>"stopped");
            }
        }

        return $response;
    }

    public function startAction()
    {
        $response = array("status"=>"fail", "message" => "Invalid request");

        if ($this->request->isPost()) {
            $backend = new Backend();
            $backend_result = trim($backend->configdRun('configsync start'));
            if (strtoupper($backend_result) == "OK") {
                $response = array("status"=>"success", "message" => "ConfigSync service started");
            }
        }
        
        return $response;
    }

    public function restartAction()
    {
        $response = array("status"=>"fail", "message" => "Invalid request");

        if ($this->request->isPost()) {
            $backend = new Backend();
            $backend_result = trim($backend->configdRun('configsync restart'));
            if (strtoupper($backend_result) == "OK") {
                $response = array("status"=>"success", "message" => "ConfigSync service stopped");
            }
        }
        
        return $response;
    }
    
    public function stopAction()
    {
        $response = array("status"=>"fail", "message" => "Invalid request");

        if ($this->request->isPost()) {
            $backend = new Backend();
            $backend_result = trim($backend->configdRun('configsync stop'));
            if (strtoupper($backend_result) == "OK") {
                $response = array("status"=>"success", "message" => "ConfigSync service stopped");
            }
        }
        
        return $response;
    }
}
