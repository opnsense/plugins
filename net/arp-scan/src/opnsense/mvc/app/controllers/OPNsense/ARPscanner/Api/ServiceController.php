<?php

/**
 *    Copyright (C) 2017 Giuseppe De Marco <giuseppe.demarco@unical.it>
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
 */

namespace OPNsense\ARPscanner\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

class ServiceController extends ApiControllerBase
{

    public function startAction()
    {
        if ($this->request->isPost()) {
            $ifname =  escapeshellarg($_POST['interface']);
            $networks =  escapeshellarg($_POST['networks']);
            //~ return 'arpscanner start '.$ifname.' '.$networks;
            $backend = new Backend();
            $result = json_decode(trim($backend->configdRun('arpscanner start '.$ifname.' '.$networks)), true);
            return $result;
        }
        return array("message" => "unable to run config action");
    }

    public function statusAction()
    {
        if ($this->request->isPost()) {
            $ifname =  escapeshellarg($_POST['interface']);
            //~ return 'arpscanner start '.$ifname.' '.$networks;
            $backend = new Backend();
            $result = json_decode(trim($backend->configdRun('arpscanner status '.$ifname)), true);
            return $result;
        }
        return array("message" => "this action must be called using the POST method");
    }

    public function stopAction()
    {
        if ($this->request->isPost()) {
            $ifname =  escapeshellarg($_POST['interface']);
            $backend = new Backend();
            $bckresult = trim($backend->configdRun("arpscanner stop ".$ifname));
            if ($bckresult !== null) {
                // only return valid json type responses
                return $bckresult;
            }
            return array("message" => "error");
        }
    }

    public function checkAction()
    {
        if ($this->request->isPost()) {
            $ifname =  escapeshellarg($_POST['interface']);
            // test: "configctl arpscanner check em0"
            $backend = new Backend();
            $bckresult = json_decode(trim($backend->configdRun("arpscanner check ".$ifname)), true);
            if ($bckresult !== null) {
                // only return valid json type responses
                return $bckresult;
            }
            return array("message" => "error");
        }
    }
}
