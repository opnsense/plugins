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
 *
**/
namespace OPNsense\ARPscanner\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\ARPscanner\ARPscanner;
use \OPNsense\Core\Config;
use \OPNsense\Core\Backend;

class SettingsController extends ApiControllerBase
{
    /* retrieve general settings
     * @return array general settings
     */
    public function getAction()
    {
    // define list of configurable settings
        $result = array();
        if ($this->request->isGet()) {
            $mdl = new ARPscanner();
            $result['arpscanner'] =  $mdl->getNodes();
            // returns: {"arpscanner":{"general":{"interface":
            // {"lan":{"value":"lan","selected":1}},"networks":
            // {"10.0.1.0\/24":{"value":"10.0.1.0\/24","selected":1}}}}}

            $backend = new Backend();
            $bckresult = trim($backend->configdRun("arpscanner interfaces"));
            $ifnames = json_decode($bckresult);

            $result['arpscanner']['general']['interface'] = array();

            if (is_array($ifnames) || is_object($ifnames)) {
                foreach ($ifnames as &$arr) {
                    $ifname = $arr[0];
                    $result['arpscanner']['general']['interface'][$ifname] = array();
                    $result['arpscanner']['general']['interface'][$ifname]['value'] = join(", ", array($ifname, " (".$arr[2].")"));
                }
            }
            // $result['arpscanner']['general']['networks'] = '192.168.1.0/24,172.16.45.0/25';
        }
        return $result;
    }

    /**
     * update arpscan settings
     * @return array status
     */
    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            // load model and update with provided data
            $mdl = new ARPscanner();
            $mdl->setNodes($this->request->getPost("arpscanner"));

            // perform validation
            $valMsgs = $mdl->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["general.".$msg->getField()] = $msg->getMessage();
            }

            // serialize model to config and save
            if ($valMsgs->count() == 0) {
                $mdl->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }
}
