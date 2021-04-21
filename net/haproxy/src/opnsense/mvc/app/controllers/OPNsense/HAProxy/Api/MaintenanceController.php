<?php

/**
 *    Copyright (C) 2021 Andreas Stuerz
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

namespace OPNsense\HAProxy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Cron\Cron;
use OPNsense\HAProxy\HAProxy;

/**
 * Class MaintenanceController
 * @package OPNsense\HAProxy
 */
class MaintenanceController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'haproxy';
    protected static $internalModelClass = '\OPNsense\HAProxy\HAProxy';

    /**
     * jQuery bootstrap certificates diff list
     * @return array|mixed
     */
    public function searchCertificateDiffAction()
    {
        $backend = new Backend();
        $backend->configdRun('template reload OPNsense/HAProxy');

        return $this->getData(
            ["cert_diff_list"],
            ["rowCount", "current", "searchPhrase", "sort"]
        );
    }

    /**
     * jQuery bootstrap server list
     * @return array|mixed
     */
    public function searchServerAction()
    {
        $backend = new Backend();
        $backend->configdRun('template reload OPNsense/HAProxy');

        return $this->getData(
            ["server_status_list"],
            ["rowCount", "current", "searchPhrase", "sort"]
        );
    }

    /**
     * sync certificate for frontends
     * @return array|mixed
     */
    public function certSyncAction()
    {
        $backend = new Backend();
        $backend->configdRun('template reload OPNsense/HAProxy');

        return $this->syncCerts(
            ["cert_sync"],
            ["frontend_ids"]
        );
    }

    /**
     * sync certificate for frontends
     * @return array|mixed
     */
    public function certSyncBulkAction()
    {
        $backend = new Backend();
        $backend->configdRun('template reload OPNsense/HAProxy');

        return $this->syncCerts(
            ["cert_sync_bulk"]
        );
    }

    /**
     * show certificate diff for frontends
     * @return array|mixed
     */
    public function certDiffAction()
    {
        $backend = new Backend();
        $backend->configdRun('template reload OPNsense/HAProxy');

        return $this->getData(
            ["cert_diff"],
            ["frontend_ids"]
        );
    }

    /**
     * show certificate actions for frontends
     * @return array|mixed
     */
    public function certActionsAction()
    {
        $backend = new Backend();
        $backend->configdRun('template reload OPNsense/HAProxy');

        return $this->getData(
            ["cert_actions"],
            ["frontend_ids"]
        );
    }

    /**
     * set server weight
     * @return array|mixed
     */
    public function serverWeightAction()
    {
        return $this->saveData(
            ["server_weight"],
            ["backend", "server", "weight"]
        );
    }

    /**
     * set server administrative state
     * @return array|mixed
     */
    public function serverStateAction()
    {
        return $this->saveData(
            ["server_state"],
            ["backend", "server", "state"]
        );
    }

    /**
     * set server administrative state for multiple servers
     * @return array|mixed
     */
    public function serverStateBulkAction()
    {
        return $this->saveData(
            ["server_state_bulk"],
            ["server_ids", "state"]
        );
    }

    /**
     * set server weight for multiple servers
     * @return array|mixed
     */
    public function serverWeightBulkAction()
    {
        return $this->saveData(
            ["server_weight_bulk"],
            ["server_ids", "weight"]
        );
    }

    /**
     * Execute a backend command securely
     * @param array $command
     * @param array $arguments
     * @return string
     */
    protected function safeBackendCmd(array $command, array $arguments = [])
    {
        $backend = new Backend();

        foreach ($arguments as $name) {
            $val = $this->request->getPost($name);
            if (is_array($val) and $name == 'sort') {
                $sort =  key(array_slice($val, 0, 1));
                $sort_dir = $val[$sort];
                $command[] = $sort;
                $command[] = $sort_dir;
                continue;
            }
            $command[] = $val;
        }

        $command = array_map(function ($value) {
            return escapeshellarg(empty($value = trim($value)) ? null : $value);
        }, $command);

        return trim($backend->configdRun("haproxy " . join(" ", $command)));
    }

    /**
     * Executes a backend command to get data
     * @param array $command
     * @param array $arguments
     * @return string|string[]
     */
    protected function getData(array $command, array $arguments = [])
    {
        if ($this->request->isPost()) {
            return $this->safeBackendCmd($command, $arguments);
        }
        return ["status" => "unavailable"];
    }

    /**
     * Executes a backend command which returns output on error
     * @param array $command
     * @param array $arguments
     * @return array|string[]
     */
    protected function saveData(array $command, array $arguments = [])
    {
        if ($this->request->isPost()) {
            if ($error = $this->safeBackendCmd($command, $arguments)) {
                return [
                    "status" => "error",
                    "message" => $error
                ];
            } else {
                return ["status" => "ok"];
            }
        }
        return [
            "status" => 'unavailable',
            "message" => 'only accept POST Requests.'
        ];
    }

    /**
     * Executes a ssl certificate sync
     * @param array $command
     * @param array $arguments
     * @return array|string[]
     */
    protected function syncCerts(array $command, array $arguments = [])
    {
        if ($this->request->isPost()) {
            $output = $this->safeBackendCmd($command, $arguments);
            $result = json_decode($output, true);

            return [
                "status" => "ok",
                "result" => $result,
            ];
        }
        return [
            "status" => 'unavailable',
            "message" => 'only accept POST Requests.'
        ];
    }

    /**
     * create new cron job or return already available one
     * @return array status action
     */
    public function fetchCronIntegrationAction()
    {
        $result = array("result" => "no change");

        if ($this->request->isPost()) {
            $mdlHaproxy = $this->getModel();
            $backend = new Backend();

            // Define possible cron jobs with their configd actions
            $cronjobs = array(
                'syncCerts' => 'cert_sync_bulk',
                'updateOcsp' => 'update_ocsp',
                'reloadService' => 'reload',
                'restartService' => 'restart',
            );

            // Iterate over all possible cron jobs
            foreach ($cronjobs as $cron => $cron_action) {
                // Name of the item that holds the cron UUID
                $cron_ref = "${cron}Cron";

                // Check if the cron job is enabled or disabled
                if ((string)$mdlHaproxy->maintenance->cronjobs->$cron == "1") {
                    // Check if a cron job already exists
                    if ((string)$mdlHaproxy->maintenance->cronjobs->$cron_ref == "") {
                        // Create new cron job
                        $mdlCron = new Cron();
                        // NOTE: Only configd actions are valid commands for cronjobs
                        //       and they *must* provide a description that is not empty.
                        $cron_uuid = $mdlCron->newDailyJob(
                            "HAProxy",
                            "haproxy ${cron_action}",
                            "Added by HAProxy plugin",
                            "*",
                            "1"
                        );
                        $mdlHaproxy->maintenance->cronjobs->$cron_ref = $cron_uuid;

                        // Save updated configuration.
                        if ($mdlCron->performValidation()->count() == 0) {
                            $mdlCron->serializeToConfig();
                            // save data to config, do not validate because the current in memory model doesn't know about the
                            // cron item just created.
                            $mdlHaproxy->serializeToConfig($validateFullModel = false, $disable_validation = true);
                            Config::getInstance()->save();
                            // Refresh the crontab
                            $backend->configdRun('template reload OPNsense/Cron');
                            // (res)start daemon
                            $backend->configdRun("cron restart");
                            $this->getLogger()->error("HAProxy: successfully created cron job $cron ($cron_uuid)");
                            $result['result'] = "new";
                            $result['uuid'] = $cron_uuid;
                        } else {
                            $this->getLogger()->error("HAProxy: unable to create cron job $cron");
                            $result['result'] = "unable to add cron";
                        }
                    }
                } else {
                    // Check if a cron job exists
                    if ((string)$mdlHaproxy->maintenance->cronjobs->$cron_ref != "") {
                        // Clean existin entry
                        $cron_uuid = (string)$mdlHaproxy->maintenance->cronjobs->$cron_ref;
                        $mdlHaproxy->maintenance->cronjobs->$cron_ref = "";

                        // Delete the cronjob item
                        $mdlCron = new Cron();
                        if ($mdlCron->jobs->job->del($cron_uuid)) {
                            // If item is removed, serialize to config and save
                            $mdlCron->serializeToConfig();
                            $mdlHaproxy->serializeToConfig($validateFullModel = false, $disable_validation = true);
                            Config::getInstance()->save();
                            // Regenerate the crontab
                            $backend->configdRun('template reload OPNsense/Cron');
                            // (res)start daemon
                            $backend->configdRun("cron restart");
                            $this->getLogger()->error("HAProxy: successfully deleted cron job $cron ($cron_uuid)");
                            $result['result'] = "deleted";
                        } else {
                            $this->getLogger()->error("HAProxy: unable to delete cron job $cron ($cron_uuid)");
                            $result['result'] = "unable to delete cron";
                        }
                    }
                }
            }
        }

        return $result;
    }
}
