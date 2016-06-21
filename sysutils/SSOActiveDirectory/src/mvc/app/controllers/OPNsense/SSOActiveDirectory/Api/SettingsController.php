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

namespace OPNsense\SSOActiveDirectory\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\SSOActiveDirectory\SSOActiveDirectory;
use \OPNsense\Core\Config;

class SettingsController extends ApiControllerBase
{
    /*
     * retrieve SSO Active Directory general settings
     * @return array general settings
     */
    public function getAction()
    {
        // define list of configurable settings
        $result = array();
            if ($this->request->isGet()) {
            $mdlSSOActiveDirectory= new SSOActiveDirectory();
            $result['ssoactiveDirectory'] = $mdlSSOActiveDirectory->getNodes();
        }
        return $result;
    }

/**
 * update SSOActiveDirectory settings
 * @return array status
 */
public function setAction()
{
    $result = array("result"=>"failed");
    if ($this->request->isPost()) {
        // load model and update with provided data
        $mdlSSOActiveDirectory= new SSOActiveDirectory();
        $mdlSSOActiveDirectory->setNodes($this->request->getPost("ssoactivedirectory"));

        // perform validation
        $valMsgs = $mdlSSOActiveDirectory->performValidation();
        foreach ($valMsgs as $field => $msg) {
            if (!array_key_exists("validations", $result)) {
                $result["validations"] = array();
            }
            $result["validations"]["general.".$msg->getField()] = $msg->getMessage();
        }

        // serialize model to config and save
        if ($valMsgs->count() == 0) {
            $mdlSSOActiveDirectory->serializeToConfig();
            Config::getInstance()->save();
            $result["result"] = "saved";
        }
    }
    return $result;
}

}
