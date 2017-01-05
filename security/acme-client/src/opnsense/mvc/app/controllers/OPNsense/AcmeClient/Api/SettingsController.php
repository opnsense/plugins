<?php
/**
 *    Copyright (C) 2017 Frank Wall
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
namespace OPNsense\AcmeClient\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Cron\Cron;
use \OPNsense\Core\Config;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\AcmeClient
 */
class SettingsController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'acmeclient';
    static protected $internalModelClass = '\OPNsense\AcmeClient\AcmeClient';

    /**
     * create new cron job or return already available one
     * @return array status action
     */
    public function fetchRBCronAction()
    {
        $result = array("result" => "no change");

        // TODO: How to force the system to write-out the cronjob?

        if ($this->request->isPost()) {
            $mdlAcme = $this->getModel();
            $backend = new Backend();

            // Setup cronjob if AcmeClient and AutoRenewal is enabled.
            if ((string)$mdlAcme->settings->UpdateCron == "" and
                (string)$mdlAcme->settings->autoRenewal == "1" and
                (string)$mdlAcme->settings->enabled == "1") {

                $mdlCron = new Cron();
                // NOTE: Only configd actions are valid commands for cronjobs
                //       and they *must* provide a description that is not empty.
                $cron_uuid = $mdlCron->newDailyJob(
                    "AcmeClient",
                    "acmeclient cron-auto-renew",
                    "AcmeClient Cronjob for Certificate AutoRenewal",
                    "*",
                    "1"
                );
                $mdlAcme->settings->UpdateCron = $cron_uuid;

                // Save updated configuration.
                if ($mdlCron->performValidation()->count() == 0) {
                    $mdlCron->serializeToConfig();
                    // save data to config, do not validate because the current in memory model doesn't know about the
                    // cron item just created.
                    $mdlAcme->serializeToConfig($validateFullModel = false, $disable_validation = true);
                    Config::getInstance()->save();
                    // Regenerate the crontab
                    $backend->configdRun('template reload OPNsense/Cron');
                    $result['result'] = "new";
                    $result['uuid'] = $cron_uuid;
                } else {
                    $result['result'] = "unable to add cron";
                }
            // Delete cronjob if AcmeClient or AutoRenewal is disabled.
            } elseif ((string)$mdlAcme->settings->UpdateCron != "" and
                ((string)$mdlAcme->settings->autoRenewal == "0" or
                (string)$mdlAcme->settings->enabled == "0")) {

                $cron_uuid = (string)$mdlAcme->settings->UpdateCron;
                $mdlAcme->settings->UpdateCron = null;
                $mdlCron = new Cron();
                if ($mdlCron->jobs->job->del($cron_uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCron->serializeToConfig();
                    $mdlAcme->serializeToConfig($validateFullModel = false, $disable_validation = true);
                    Config::getInstance()->save();
                    // Regenerate the crontab
                    $backend->configdRun('template reload OPNsense/Cron');
                    $result['result'] = "deleted";
                } else {
                    $result['result'] = "unable to delete cron";
                }
            }
        }

        return $result;
    }
}
