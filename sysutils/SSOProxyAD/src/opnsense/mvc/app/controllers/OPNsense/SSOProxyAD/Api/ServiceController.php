<?php
/**
 *    Copyright (C) 2016 gitdevmod@github.com
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

namespace OPNsense\SSOProxyAD\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\SSOProxyAD\SSOProxyAD;
use \OPNsense\Core\Backend;
use \OPNsense\Cron\Cron;
class ServiceController extends ApiControllerBase
{

public function reloadAction()
{
    $status = "failed";
    if ($this->request->isPost()) {
        $mdlSSOProxyAD = new SSOProxyAD();
        if ((string)$mdlSSOProxyAD->general->UpdateCron == "") {
            $mdlCron = new Cron();
            $mdlSSOProxyAD->general->UpdateCron = $mdlCron->newDailyJob("SSOProyAD", "ssoproxyad updateDomain", "SSOProxyAD updateDomain cron", "1");
                if ($mdlCron->performValidation()->count() == 0) {
                    $mdlCron->serializeToConfig();
                    $mdlMymodule->serializeToConfig($validateFullModel = false, $disable_validation = true);
                    Config::getInstance()->save();
                }
        }
        $backend = new Backend();
        $bckresult = trim($backend->configdRun("template reload OPNsense.SSOProxyAD"));
        if ($bckresult == "OK") {
            $status = "ok";
        }
    }
    return array("status" => $status);
}

public function testAction()
{
    if ($this->request->isPost()) {
        $backend = new Backend();
        $bckresult = json_decode(trim($backend->configdRun("ssoproxyad test")), true);
        if ($bckresult !== null) {
            // only return valid json type responses
            return $bckresult;
        }
    }
    return array("message" => "unable to run config action");
}

public function joinDomainAction()
{
    if ($this->request->isPost()) {
        $backend = new Backend();
        $bckresult = json_decode(trim($backend->configdRun("ssoproxyad joinDomain")), true);
        if ($bckresult !== null) {
            // only return valid json type responses
            return $bckresult;
        }
    }
    return array("message" => "unable to run config action");
}

public function updateDomainAction()
{
    if ($this->request->isPost()) {
        $backend = new Backend();
        $bckresult = json_decode(trim($backend->configdRun("ssoproxyad updateDomain")), true);
        if ($bckresult !== null) {
            // only return valid json type responses
            return $bckresult;
        }
    }
    return array("message" => "unable to run config action");
}

}
