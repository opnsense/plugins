<?php

/**
 *    Copyright (C) 2017-2021 Frank Wall
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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Cron\Cron;
use OPNsense\Core\Config;
use OPNsense\Base\UIModelGrid;
use OPNsense\AcmeClient\AcmeClient;

/**
 * Class SettingsController
 * @package OPNsense\AcmeClient
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'acmeclient';
    protected static $internalModelClass = '\OPNsense\AcmeClient\AcmeClient';

    /**
     * create new cron job or return already available one
     * @return array status action
     */
    public function fetchCronIntegrationAction()
    {
        $result = array("result" => "no change");

        if ($this->request->isPost()) {
            $mdlAcme = $this->getModel();
            $backend = new Backend();

            // Setup cronjob if AcmeClient and AutoRenewal is enabled.
            if (
                (string)$mdlAcme->settings->UpdateCron == "" and
                (string)$mdlAcme->settings->autoRenewal == "1" and
                (string)$mdlAcme->settings->enabled == "1"
            ) {
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
                    // Refresh the crontab
                    $backend->configdRun('template reload OPNsense/Cron');
                    // (res)start daemon
                    $backend->configdRun("cron restart");
                    $result['result'] = "new";
                    $result['uuid'] = $cron_uuid;
                } else {
                    $result['result'] = "unable to add cron";
                }
            // Delete cronjob if AcmeClient or AutoRenewal is disabled.
            } elseif (
                (string)$mdlAcme->settings->UpdateCron != "" and
                ((string)$mdlAcme->settings->autoRenewal == "0" or
                (string)$mdlAcme->settings->enabled == "0")
            ) {
                // Get UUID, clean existin entry
                $cron_uuid = (string)$mdlAcme->settings->UpdateCron;
                $mdlAcme->settings->UpdateCron = "";
                $mdlCron = new Cron();
                // Delete the cronjob item
                if ($mdlCron->jobs->job->del($cron_uuid)) {
                    // If item is removed, serialize to config and save
                    $mdlCron->serializeToConfig();
                    $mdlAcme->serializeToConfig($validateFullModel = false, $disable_validation = true);
                    Config::getInstance()->save();
                    // Regenerate the crontab
                    $backend->configdRun('template reload OPNsense/Cron');
                    // (res)start daemon
                    $backend->configdRun("cron restart");
                    $result['result'] = "deleted";
                } else {
                    $result['result'] = "unable to delete cron";
                }
            }
        }

        return $result;
    }

    /**
     * integrate with HAProxy plugin or return if already done
     * @return array status action
     */
    public function fetchHAProxyIntegrationAction()
    {
        $result = array("result" => "no change");

        if ($this->request->isPost()) {
            $mdlAcme = $this->getModel();

            // Check if the required plugin is installed
            if ((string)$mdlAcme->isPluginInstalled('haproxy') != "1") {
                $this->getLogger()->error("AcmeClient: HAProxy plugin is NOT installed, skipping integration");
                return($result);
            }

            // Setup only if AcmeClient and HAProxy integration is enabled.
            // NOTE: We provide HAProxy integration no matter if the HAProxy plugin
            //       is actually enabled or not. This should avoid confusion.
            if (
                (string)$mdlAcme->settings->haproxyIntegration == "1" and
                (string)$mdlAcme->settings->enabled == "1"
            ) {
                $mdlHAProxy = new \OPNsense\HAProxy\HAProxy();
                $backend = new Backend();

                // Get current status of HAProxy integration by running various checks.
                $integration_found = false; // Switch to TRUE if something is found.
                $integration_complete = true; // Switch to FALSE if anything is missing.
                $integration_changes = false; // Switch to TRUE if config was changes.

                // Check: HAProxy ACL
                $acl_ref = (string)$mdlAcme->settings->haproxyAclRef;
                if (!empty($acl_ref)) {
                    $integration_found = true; // We found something.
                    // Make sure the item was not deleted.
                    if ($mdlHAProxy->getByAclID($acl_ref) === null) {
                        $this->getLogger()->error("AcmeClient: HAProxy integration is incomplete: ACL item not found");
                        $integration_complete = false; // Item is broken.
                    }
                } else {
                    $integration_complete = false; // Item is missing.
                }

                // Check: HAProxy action
                $action_ref = (string)$mdlAcme->settings->haproxyActionRef;
                if (!empty($action_ref)) {
                    $integration_found = true; // We found something.
                    // Make sure the item was not deleted.
                    if ($mdlHAProxy->getByActionID($action_ref) === null) {
                        $this->getLogger()->error("AcmeClient: HAProxy integration is incomplete: action item not found");
                        $integration_complete = false; // Item is broken.
                    }
                } else {
                    $integration_complete = false; // Item is missing.
                }

                // Check: HAProxy server
                $server_ref = (string)$mdlAcme->settings->haproxyServerRef;
                if (!empty($server_ref)) {
                    $integration_found = true; // We found something.
                    // Make sure the item was not deleted.
                    if ($mdlHAProxy->getByServerID($server_ref) === null) {
                        $this->getLogger()->error("AcmeClient: HAProxy integration is incomplete: server item not found");
                        $integration_complete = false; // Item is broken.
                    }
                } else {
                    $integration_complete = false; // Item is missing.
                }

                // Check: HAProxy backend
                $backend_ref = (string)$mdlAcme->settings->haproxyBackendRef;
                if (!empty($backend_ref)) {
                    $integration_found = true; // We found something.
                    // Make sure the item was not deleted.
                    if ($mdlHAProxy->getByBackendID($backend_ref) === null) {
                        $this->getLogger()->error("AcmeClient: HAProxy integration is incomplete: backend item not found");
                        $integration_complete = false; // Item is broken.
                    }
                } else {
                    $integration_complete = false; // Item is missing.
                }

                // Check if HAProxy integration is already complete.
                if ($integration_found and $integration_complete) {
                    $this->getLogger()->error("AcmeClient: HAProxy integration is complete");
                } else {
                    $integration_changes = true;
                    /**
                     * Check if we need to remove relics of incomplete HAProxy integration.
                     * NOTE: We try to automatically repair a broken HAProxy integration,
                     *       although the user may have deleted some items intentionally.
                     *       As long as the HAProxy integration is enabled we assume that
                     *       this is an error that should *automatically* be fixed.
                     */
                    if ($integration_found and !$integration_complete) {
                        // NOTE: We ignore the return value of the del() calls
                        //       too keep this as simple as possible.
                        $this->getLogger()->error("AcmeClient: HAProxy integration is incomplete, removing relics");
                        // Remove obsolete backend item
                        if (!empty($backend_ref)) {
                            if ($mdlHAProxy->backends->backend->del($backend_ref)) {
                                $this->getLogger()->error("AcmeClient: HAProxy integration: deleted obsolete backend item");
                            }
                        }
                        // Remove obsolete server item
                        if (!empty($server_ref)) {
                            if ($mdlHAProxy->servers->server->del($server_ref)) {
                                $this->getLogger()->error("AcmeClient: HAProxy integration: deleted obsolete server item");
                            }
                        }
                        // Remove obsolete action item
                        if (!empty($action_ref)) {
                            if ($mdlHAProxy->actions->action->del($action_ref)) {
                                $this->getLogger()->error("AcmeClient: HAProxy integration: deleted obsolete action item");
                            }
                        }
                        // Remove obsolete ACL item
                        if (!empty($acl_ref)) {
                            if ($mdlHAProxy->acls->acl->del($acl_ref)) {
                                $this->getLogger()->error("AcmeClient: HAProxy integration: deleted obsolete ACL item");
                            }
                        }
                        // TODO: Remove obsolete ACL link from frontends

                        // NOTE: We don't clear the settings refs here, because they
                        //       will be overwritten later anyway.
                        $result['result'] = "repaired";
                    } else {
                        $this->getLogger()->error("AcmeClient: HAProxy integration initializing");
                        $result['result'] = "new";
                    }

                    // Get TCP port for internal acme webserver from config.
                    $acme_port = (string)$mdlAcme->settings->challengePort;

                    // Add a new HAProxy ACL
                    $acl_uuid = $mdlHAProxy->newAcl(
                        "find_acme_challenge",
                        "path_beg",
                        "Added by ACME Client plugin",
                        "0",
                        array("path_beg" => "/.well-known/acme-challenge/")
                    );

                    // Add a new HAProxy backend
                    $backend_uuid = $mdlHAProxy->newBackend(
                        "acme_challenge_backend",
                        "http",
                        "source",
                        "1",
                        "Added by ACME Client plugin",
                        "",
                        ""
                    );

                    // Add a new HAProxy action
                    $action_uuid = $mdlHAProxy->newAction(
                        "redirect_acme_challenges",
                        "if",
                        "use_backend",
                        "Added by ACME Client plugin",
                        "",
                        "and",
                        // Use the new backend uuid in field "useBackend"
                        array("use_backend" => $backend_uuid)
                    );

                    // NOTE: This action is linked to frontends.
                    $action_ref = $action_uuid;

                    // Add a new HAProxy server
                    $server_uuid = $mdlHAProxy->newServer(
                        "acme_challenge_host",
                        "127.0.0.1",
                        $acme_port,
                        "active",
                        "Added by ACME Client plugin",
                        "0",
                        "0",
                        ""
                    );

                    // Update hidden fields to signal that HAProxy integration is complete.
                    $mdlAcme->settings->haproxyAclRef = $acl_uuid;
                    $mdlAcme->settings->haproxyActionRef = $action_uuid;
                    $mdlAcme->settings->haproxyServerRef = $server_uuid;
                    $mdlAcme->settings->haproxyBackendRef = $backend_uuid;

                    // Link new ACL to HAProxy action
                    $link_acl_result = $mdlHAProxy->linkAclToAction($acl_uuid, $action_uuid);

                    // Link new server to HAProxy backend
                    $link_server_result = $mdlHAProxy->linkServerToBackend($server_uuid, $backend_uuid);
                }

                // Ensure HAProxy frontend additions have been applied.
                foreach ($mdlAcme->getNodeByReference('validations.validation')->iterateItems() as $validation) {
                    // Find all (enabled) validation methods with HAProxy integration.
                    if (
                        (string)$validation->enabled == "1" and
                        (string)$validation->method == "http01" and
                        (string)$validation->http_service == "haproxy"
                    ) {
                        // Check if HAProxy frontends were specified.
                        if (empty((string)$validation->http_haproxyFrontends)) {
                            // Skip item, no HAProxy frontends were specified.
                            continue;
                        }
                        $_frontends = explode(',', $validation->http_haproxyFrontends);
                        // Walk through all linked frontends.
                        foreach ($_frontends as $_frontend) {
                            $frontend = $mdlHAProxy->getByFrontendID($_frontend);
                            // Make sure the frontend was found in config.
                            if (!is_null($frontend) && !empty((string)$frontend->id)) {
                                // Check if the HAProxy ACME Action is linked to this frontend.
                                $_actions = $frontend->linkedActions;
                                if (strpos($_actions, $action_ref) !== false) {
                                    // Match! Nothing to do.
                                } else {
                                    // Link to ACME Action is currently missing: add it!
                                    if (!empty((string)$_actions)) {
                                        // Extend existing string.
                                        $_actions .= ",${action_ref}";
                                    } else {
                                        // First linked Action for this frontend.
                                        $_actions = $action_ref;
                                    }
                                    // Add modified list of linked Actions to frontend.
                                    $frontend->linkedActions = $_actions;
                                    $this->getLogger()->error("AcmeClient: HAProxy integration: updating frontend ${_frontend}");
                                    // We need to write changes to config.
                                    $integration_changes = true;
                                }
                            }
                        }
                    }
                }

                // Changes made to configuration?
                if ($integration_changes === true) {
                    $this->getLogger()->error("AcmeClient: HAProxy integration: saving updated configuration");
                    // Save updated configuration.
                    // Do NOT validate because the current in-memory model doesn't know about the
                    // HAProxy items just created.
                    // FIXME: works, but still leads to "Related item not found" errors in the log file
                    $mdlHAProxy->serializeToConfig($validateFullModel = false, $disable_validation = true);
                    $mdlAcme->serializeToConfig($validateFullModel = false, $disable_validation = true);
                    Config::getInstance()->save();

                    // Reconfigure HAProxy
                    $backend->configdRun('template reload OPNsense/HAProxy');
                    $response = $backend->configdRun("haproxy restart");
                }
            } else {
                // NOTE: HAProxy integration is NOT removed if the user disables it, because
                // we might destroy changes made by the user when doing so.
            }
        }

        return $result;
    }

    /**
     * Check whether the Google Cloud plugin is installed.
     * @return array status action
     */
    public function getGcloudPluginStatusAction()
    {
        $result = array("result" => "0");

        $mdlAcme = $this->getModel();

        // Check if the required plugin is installed
        if ((string)$mdlAcme->isPluginInstalled('google-cloud-sdk') == "1") {
            $result['result'] = "1";
        }

        return $result;
    }

    /**
     * Check whether the BIND plugin is installed.
     * @return array status action
     */
    public function getBindPluginStatusAction()
    {
        $result = array("result" => "0");

        $mdlAcme = $this->getModel();

        // Check if the required plugin is installed
        if ((string)$mdlAcme->isPluginInstalled('bind') == "1") {
            $result['result'] = "1";
        }

        return $result;
    }
}
