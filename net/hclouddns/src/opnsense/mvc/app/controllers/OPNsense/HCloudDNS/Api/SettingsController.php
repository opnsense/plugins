<?php

/**
 *    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
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

namespace OPNsense\HCloudDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

/**
 * Class SettingsController
 * @package OPNsense\HCloudDNS\Api
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\HCloudDNS\HCloudDNS';
    protected static $internalModelName = 'hclouddns';

    /**
     * Get full settings including all dropdown options
     * @return array
     */
    public function getAction()
    {
        $result = [];
        $mdl = $this->getModel();
        $result['hclouddns'] = $mdl->getNodes();
        return $result;
    }

    /**
     * Set settings
     * @return array
     */
    public function setAction()
    {
        $result = ['status' => 'error', 'message' => 'Invalid request'];
        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            $mdl->setNodes($this->request->getPost('hclouddns'));
            $valMsgs = $mdl->performValidation();
            if ($valMsgs->count() == 0) {
                $mdl->serializeToConfig();
                \OPNsense\Core\Config::getInstance()->save();
                $result = ['status' => 'ok'];
            } else {
                $result = ['status' => 'error', 'validations' => []];
                foreach ($valMsgs as $msg) {
                    $result['validations'][$msg->getField()] = $msg->getMessage();
                }
            }
        }
        return $result;
    }

    /**
     * Get general settings
     * @return array
     */
    public function getGeneralAction()
    {
        return $this->getBase('general', 'general');
    }

    /**
     * Set general settings
     * @return array
     */
    public function setGeneralAction()
    {
        return $this->setBase('general', 'general');
    }

    /**
     * Export configuration as JSON
     * @param string $include_tokens Pass '1' to include API tokens
     * @return array
     */
    public function exportAction($include_tokens = '0')
    {
        $mdl = $this->getModel();
        $includeTokens = $include_tokens === '1';

        $export = [
            'version' => '2.0.0',
            'exported' => date('c'),
            'general' => [],
            'notifications' => [],
            'gateways' => [],
            'accounts' => [],
            'entries' => []
        ];

        // Export general settings
        $general = $mdl->general;
        $export['general'] = [
            'enabled' => (string)$general->enabled,
            'checkInterval' => (string)$general->checkInterval,
            'forceInterval' => (string)$general->forceInterval,
            'verbose' => (string)$general->verbose,
            'failoverEnabled' => (string)$general->failoverEnabled,
            'failbackEnabled' => (string)$general->failbackEnabled,
            'failbackDelay' => (string)$general->failbackDelay,
            'cronEnabled' => (string)$general->cronEnabled,
            'cronInterval' => (string)$general->cronInterval,
            'historyRetentionDays' => (string)$general->historyRetentionDays
        ];

        // Export notification settings
        $notifications = $mdl->notifications;
        $export['notifications'] = [
            'enabled' => (string)$notifications->enabled,
            'notifyOnUpdate' => (string)$notifications->notifyOnUpdate,
            'notifyOnFailover' => (string)$notifications->notifyOnFailover,
            'notifyOnFailback' => (string)$notifications->notifyOnFailback,
            'notifyOnError' => (string)$notifications->notifyOnError,
            'emailEnabled' => (string)$notifications->emailEnabled,
            'emailTo' => (string)$notifications->emailTo,
            'webhookEnabled' => (string)$notifications->webhookEnabled,
            'webhookUrl' => (string)$notifications->webhookUrl,
            'webhookMethod' => (string)$notifications->webhookMethod,
            'ntfyEnabled' => (string)$notifications->ntfyEnabled,
            'ntfyServer' => (string)$notifications->ntfyServer,
            'ntfyTopic' => (string)$notifications->ntfyTopic,
            'ntfyPriority' => (string)$notifications->ntfyPriority
        ];

        // Export gateways
        foreach ($mdl->gateways->gateway->iterateItems() as $uuid => $gw) {
            $export['gateways'][] = [
                'uuid' => $uuid,
                'enabled' => (string)$gw->enabled,
                'name' => (string)$gw->name,
                'interface' => (string)$gw->interface,
                'priority' => (string)$gw->priority,
                'checkipMethod' => (string)$gw->checkipMethod,
                'healthCheckTarget' => (string)$gw->healthCheckTarget
            ];
        }

        // Export accounts (token only if explicitly requested)
        foreach ($mdl->accounts->account->iterateItems() as $uuid => $acc) {
            $accData = [
                'uuid' => $uuid,
                'enabled' => (string)$acc->enabled,
                'name' => (string)$acc->name,
                'description' => (string)$acc->description,
                'apiType' => (string)$acc->apiType
            ];
            if ($includeTokens) {
                $accData['apiToken'] = (string)$acc->apiToken;
            }
            $export['accounts'][] = $accData;
        }

        // Export entries
        foreach ($mdl->entries->entry->iterateItems() as $uuid => $entry) {
            $export['entries'][] = [
                'uuid' => $uuid,
                'enabled' => (string)$entry->enabled,
                'account' => (string)$entry->account,
                'zoneId' => (string)$entry->zoneId,
                'zoneName' => (string)$entry->zoneName,
                'recordId' => (string)$entry->recordId,
                'recordName' => (string)$entry->recordName,
                'recordType' => (string)$entry->recordType,
                'primaryGateway' => (string)$entry->primaryGateway,
                'failoverGateway' => (string)$entry->failoverGateway,
                'ttl' => (string)$entry->ttl
            ];
        }

        return [
            'status' => 'ok',
            'export' => $export
        ];
    }

    /**
     * Import configuration from JSON
     * @return array
     */
    public function importAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $importData = $this->request->getPost('import');
        if (empty($importData)) {
            return ['status' => 'error', 'message' => 'No import data provided'];
        }

        // Parse JSON if string
        if (is_string($importData)) {
            $importData = json_decode($importData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['status' => 'error', 'message' => 'Invalid JSON: ' . json_last_error_msg()];
            }
        }

        $mdl = $this->getModel();
        $imported = ['gateways' => 0, 'accounts' => 0, 'entries' => 0];
        $errors = [];

        // Import general settings
        if (isset($importData['general'])) {
            $gen = $importData['general'];
            if (isset($gen['enabled'])) $mdl->general->enabled = $gen['enabled'];
            if (isset($gen['checkInterval'])) $mdl->general->checkInterval = $gen['checkInterval'];
            if (isset($gen['forceInterval'])) $mdl->general->forceInterval = $gen['forceInterval'];
            if (isset($gen['verbose'])) $mdl->general->verbose = $gen['verbose'];
            if (isset($gen['failoverEnabled'])) $mdl->general->failoverEnabled = $gen['failoverEnabled'];
            if (isset($gen['failbackEnabled'])) $mdl->general->failbackEnabled = $gen['failbackEnabled'];
            if (isset($gen['failbackDelay'])) $mdl->general->failbackDelay = $gen['failbackDelay'];
            if (isset($gen['cronEnabled'])) $mdl->general->cronEnabled = $gen['cronEnabled'];
            if (isset($gen['cronInterval'])) $mdl->general->cronInterval = $gen['cronInterval'];
            if (isset($gen['historyRetentionDays'])) $mdl->general->historyRetentionDays = $gen['historyRetentionDays'];
        }

        // Import notification settings
        if (isset($importData['notifications'])) {
            $notif = $importData['notifications'];
            if (isset($notif['enabled'])) $mdl->notifications->enabled = $notif['enabled'];
            if (isset($notif['notifyOnUpdate'])) $mdl->notifications->notifyOnUpdate = $notif['notifyOnUpdate'];
            if (isset($notif['notifyOnFailover'])) $mdl->notifications->notifyOnFailover = $notif['notifyOnFailover'];
            if (isset($notif['notifyOnFailback'])) $mdl->notifications->notifyOnFailback = $notif['notifyOnFailback'];
            if (isset($notif['notifyOnError'])) $mdl->notifications->notifyOnError = $notif['notifyOnError'];
            if (isset($notif['emailEnabled'])) $mdl->notifications->emailEnabled = $notif['emailEnabled'];
            if (isset($notif['emailTo'])) $mdl->notifications->emailTo = $notif['emailTo'];
            if (isset($notif['webhookEnabled'])) $mdl->notifications->webhookEnabled = $notif['webhookEnabled'];
            if (isset($notif['webhookUrl'])) $mdl->notifications->webhookUrl = $notif['webhookUrl'];
            if (isset($notif['webhookMethod'])) $mdl->notifications->webhookMethod = $notif['webhookMethod'];
            if (isset($notif['ntfyEnabled'])) $mdl->notifications->ntfyEnabled = $notif['ntfyEnabled'];
            if (isset($notif['ntfyServer'])) $mdl->notifications->ntfyServer = $notif['ntfyServer'];
            if (isset($notif['ntfyTopic'])) $mdl->notifications->ntfyTopic = $notif['ntfyTopic'];
            if (isset($notif['ntfyPriority'])) $mdl->notifications->ntfyPriority = $notif['ntfyPriority'];
        }

        // Map old UUIDs to new UUIDs for reference updating
        $gatewayMap = [];
        $accountMap = [];

        // Import gateways
        if (isset($importData['gateways']) && is_array($importData['gateways'])) {
            foreach ($importData['gateways'] as $gwData) {
                $gw = $mdl->gateways->gateway->Add();
                $newUuid = $gw->getAttributes()['uuid'];
                if (isset($gwData['uuid'])) {
                    $gatewayMap[$gwData['uuid']] = $newUuid;
                }
                $gw->enabled = $gwData['enabled'] ?? '1';
                $gw->name = $gwData['name'] ?? '';
                $gw->interface = $gwData['interface'] ?? '';
                $gw->priority = $gwData['priority'] ?? '10';
                $gw->checkipMethod = $gwData['checkipMethod'] ?? 'web_ipify';
                $gw->healthCheckTarget = $gwData['healthCheckTarget'] ?? '8.8.8.8';
                $imported['gateways']++;
            }
        }

        // Import accounts
        if (isset($importData['accounts']) && is_array($importData['accounts'])) {
            foreach ($importData['accounts'] as $accData) {
                // Skip accounts without tokens (they can't function)
                if (empty($accData['apiToken'])) {
                    $errors[] = "Account '{$accData['name']}' skipped - no API token";
                    continue;
                }
                $acc = $mdl->accounts->account->Add();
                $newUuid = $acc->getAttributes()['uuid'];
                if (isset($accData['uuid'])) {
                    $accountMap[$accData['uuid']] = $newUuid;
                }
                $acc->enabled = $accData['enabled'] ?? '1';
                $acc->name = $accData['name'] ?? '';
                $acc->description = $accData['description'] ?? '';
                $acc->apiType = $accData['apiType'] ?? 'cloud';
                $acc->apiToken = $accData['apiToken'];
                $imported['accounts']++;
            }
        }

        // Import entries (update references to new gateway/account UUIDs)
        if (isset($importData['entries']) && is_array($importData['entries'])) {
            foreach ($importData['entries'] as $entryData) {
                // Map old UUIDs to new ones
                $accountUuid = $entryData['account'] ?? '';
                $primaryGwUuid = $entryData['primaryGateway'] ?? '';
                $failoverGwUuid = $entryData['failoverGateway'] ?? '';

                if (isset($accountMap[$accountUuid])) {
                    $accountUuid = $accountMap[$accountUuid];
                }
                if (isset($gatewayMap[$primaryGwUuid])) {
                    $primaryGwUuid = $gatewayMap[$primaryGwUuid];
                }
                if (!empty($failoverGwUuid) && isset($gatewayMap[$failoverGwUuid])) {
                    $failoverGwUuid = $gatewayMap[$failoverGwUuid];
                }

                $entry = $mdl->entries->entry->Add();
                $entry->enabled = $entryData['enabled'] ?? '1';
                $entry->account = $accountUuid;
                $entry->zoneId = $entryData['zoneId'] ?? '';
                $entry->zoneName = $entryData['zoneName'] ?? '';
                $entry->recordId = $entryData['recordId'] ?? '';
                $entry->recordName = $entryData['recordName'] ?? '';
                $entry->recordType = $entryData['recordType'] ?? 'A';
                $entry->primaryGateway = $primaryGwUuid;
                $entry->failoverGateway = $failoverGwUuid;
                $entry->ttl = $entryData['ttl'] ?? '300';
                $entry->status = 'pending';
                $imported['entries']++;
            }
        }

        // Validate and save
        $valMsgs = $mdl->performValidation();
        if ($valMsgs->count() > 0) {
            foreach ($valMsgs as $msg) {
                $errors[] = $msg->getField() . ': ' . $msg->getMessage();
            }
        }

        $mdl->serializeToConfig();
        \OPNsense\Core\Config::getInstance()->save();

        return [
            'status' => 'ok',
            'imported' => $imported,
            'errors' => $errors,
            'message' => sprintf(
                'Imported %d gateways, %d accounts, %d entries',
                $imported['gateways'],
                $imported['accounts'],
                $imported['entries']
            )
        ];
    }
}
