<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\vephw\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;


class ServiceController extends ApiMutableServiceControllerBase
{

    protected static $internalServiceClass = '\OPNsense\vephw\vephw';
    //protected static $internalServiceTemplate = 'OPNsense/vephw';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceName = 'vephw';
    
    
    public function startAction()
    {
        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            $boardtype = trim((new Backend())->configdRun('vephw getid'));
            $boardnibble = substr($boardtype, -1);
            if ($boardnibble != "0" && $boardnibble != "1"){
                $fanc_enabled = $mdl->general->FanControl;
                $fan_dc = $mdl->general->FanDutyCycle;
                $fanresult = trim((new Backend())->configdRun(sprintf("vephw setfan %s %s", $fanc_enabled, $fan_dc)));
            }
            else {
                $fanresult = "OK"; //we can't set fan settings on boards without them so fake an OK
            }
            $ledr = $mdl->led->red; $ledg = $mdl->led->green; $ledb = $mdl->led->blue;
            $ledresult = trim((new Backend())->configdRun(sprintf("vephw setled %s %s %s", $ledr, $ledg, $ledb)));
        }
    }

    /**
     * reconfigure vephw
     */
    public function reloadAction()
    {
        $status = "success";
        return ["status" => $status];
    }//*/

    /**
     * apply
     */
    public function applyAction()
    {
        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            $boardtype = trim((new Backend())->configdRun('vephw getid'));
            $boardnibble = substr($boardtype, -1);
            if ($boardnibble != "0" && $boardnibble != "1"){
                $fanc_enabled = $mdl->general->FanControl;
                $fan_dc = $mdl->general->FanDutyCycle;
                $fanresult = trim((new Backend())->configdRun(sprintf("vephw setfan %s %s", $fanc_enabled, $fan_dc)));
            }
            else {
                $fanresult = "OK"; //we can't set fan settings on boards without them so fake an OK
            }
            $ledr = $mdl->led->red; $ledg = $mdl->led->green; $ledb = $mdl->led->blue;
            $ledresult = trim((new Backend())->configdRun(sprintf("vephw setled %s %s %s", $ledr, $ledg, $ledb)));
            if ($fanresult == "OK" && $ledresult == "OK") {
                // only return valid json type responses
                return ["message" => "Hardware settings applied successfully"];
            }
        }
        return ["message" => "Hardware settings weren't applied."];
    }
}
