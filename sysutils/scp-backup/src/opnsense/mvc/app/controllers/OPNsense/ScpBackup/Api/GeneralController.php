<?php

/**
 *    Copyright (C) 2018 David Harrigan
 *    Copyright (C) 2015 - 2017 Deciso B.V.
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

namespace OPNsense\ScpBackup\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Core\Config;
use \OPNsense\Cron\Cron;
use \OPNsense\ScpBackup\General;

class GeneralController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'general';
    static protected $internalModelClass = 'OPNsense\ScpBackup\General';

    public function getAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdlGeneral = $this->getModel();
            $publicKey = fopen("/conf/sshd/ssh_host_rsa_key.pub", "r");
            if ($publicKey) {
                $mdlGeneral->publickey = fread($publicKey, filesize("/conf/sshd/ssh_host_rsa_key.pub"));
                fclose($publicKey);
            }
            $result['general'] = $mdlGeneral->getNodes();
        }
        return $result;
    }

    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlGeneral = $this->getModel();
            $backend = new Backend();
            $mdlCron = new Cron();
            $mdlGeneral->setNodes($this->request->getPost("general"));
            $mdlGeneral->publickey = null;
            $valMsgs = $mdlGeneral->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validation", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"][$msg->getField()] = $msg->getMessage();
            }
            if ($valMsgs->count() == 0) {
                if ($mdlGeneral->cronuuid->__toString() == "" and $mdlGeneral->enabled->__toString() == "1") {
                    // First Time Save
                    $cronUuid = $mdlCron->newDailyJob("ScpBackup", "scpbackup perform", "Backup config using SCP", "*", "1");
                    if ($mdlCron->performValidation()->count() == 0) {
                        $mdlCron->serializeToConfig();
                        // save data to config, do not validate because the current in memory model doesn't know about the cron item just created.
                        $mdlGeneral->cronuuid = $cronUuid;
                        $mdlGeneral->serializeToConfig($validateFullModel = false, $disable_validation = true);
                        Config::getInstance()->save();
                        $backend->configdRun('template reload OPNsense/Cron');
                        $result["result"] = "cron job [" . $cronUuid . "] created to backup config file daily using SCP.";
                    }
                } elseif ($mdlGeneral->cronuuid->__toString() != "" and $mdlGeneral->enabled->__toString() == "0") {
                    // Removal of Cron Job and deactivation of the backup
                    $cronUuid = $mdlGeneral->cronuuid->__toString();
                    if ($mdlCron->jobs->job->del($cronUuid)) {
                        $mdlCron->serializeToConfig();
                        $mdlGeneral->cronuuid = null;
                        $mdlGeneral->serializeToConfig($validateFullModel = false, $disable_validation = true);
                        Config::getInstance()->save();
                        $backend->configdRun('template reload OPNsense/Cron');
                        $result["result"] = "cron job [" . $cronUuid . "] to backup config file deleted.";
                    } else {
                        $result["result"] = "unable to delete cron job [". $cronUuid . "]";
                    }
                } else {
                    // Update the backup configuration
                    $mdlGeneral->serializeToConfig();
                    Config::getInstance()->save();
                    $result["result"] = "SCP backup configuration updated.";
                }
            }
        }
        return $result;
    }
}
