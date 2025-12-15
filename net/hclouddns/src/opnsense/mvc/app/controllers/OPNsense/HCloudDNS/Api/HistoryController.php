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
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * Class HistoryController
 * @package OPNsense\HCloudDNS\Api
 */
class HistoryController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'hclouddns';
    protected static $internalModelClass = 'OPNsense\HCloudDNS\HCloudDNS';

    /**
     * Search history entries
     * @return array
     */
    public function searchItemAction()
    {
        $mdl = $this->getModel();
        $retentionDays = (int)$mdl->general->historyRetentionDays;
        $cutoffTime = time() - ($retentionDays * 86400);

        $result = [
            'rows' => [],
            'rowCount' => 0,
            'total' => 0,
            'current' => 1
        ];

        foreach ($mdl->history->change->iterateItems() as $uuid => $change) {
            $timestamp = (int)(string)$change->timestamp;

            // Skip entries older than retention period
            if ($timestamp < $cutoffTime) {
                continue;
            }

            $result['rows'][] = [
                'uuid' => $uuid,
                'timestamp' => $timestamp,
                'timestampFormatted' => date('Y-m-d H:i:s', $timestamp),
                'action' => (string)$change->action,
                'accountUuid' => (string)$change->accountUuid,
                'accountName' => (string)$change->accountName,
                'zoneId' => (string)$change->zoneId,
                'zoneName' => (string)$change->zoneName,
                'recordName' => (string)$change->recordName,
                'recordType' => (string)$change->recordType,
                'oldValue' => (string)$change->oldValue,
                'oldTtl' => (string)$change->oldTtl,
                'newValue' => (string)$change->newValue,
                'newTtl' => (string)$change->newTtl,
                'reverted' => (string)$change->reverted
            ];
        }

        // Sort by timestamp descending (newest first)
        usort($result['rows'], function ($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        $result['rowCount'] = count($result['rows']);
        $result['total'] = count($result['rows']);

        return $result;
    }

    /**
     * Get a single history entry
     * @param string $uuid
     * @return array
     */
    public function getItemAction($uuid)
    {
        $mdl = $this->getModel();
        $node = $mdl->getNodeByReference('history.change.' . $uuid);

        if ($node === null) {
            return ['status' => 'error', 'message' => 'History entry not found'];
        }

        return [
            'status' => 'ok',
            'change' => [
                'uuid' => $uuid,
                'timestamp' => (int)(string)$node->timestamp,
                'timestampFormatted' => date('Y-m-d H:i:s', (int)(string)$node->timestamp),
                'action' => (string)$node->action,
                'accountUuid' => (string)$node->accountUuid,
                'accountName' => (string)$node->accountName,
                'zoneId' => (string)$node->zoneId,
                'zoneName' => (string)$node->zoneName,
                'recordName' => (string)$node->recordName,
                'recordType' => (string)$node->recordType,
                'oldValue' => (string)$node->oldValue,
                'oldTtl' => (string)$node->oldTtl,
                'newValue' => (string)$node->newValue,
                'newTtl' => (string)$node->newTtl,
                'reverted' => (string)$node->reverted
            ]
        ];
    }

    /**
     * Revert a history entry (undo the change)
     * @param string $uuid
     * @return array
     */
    public function revertAction($uuid)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $mdl = $this->getModel();
        $node = $mdl->getNodeByReference('history.change.' . $uuid);

        if ($node === null) {
            return ['status' => 'error', 'message' => 'History entry not found'];
        }

        if ((string)$node->reverted === '1') {
            return ['status' => 'error', 'message' => 'This change has already been reverted'];
        }

        $action = (string)$node->action;
        $accountUuid = (string)$node->accountUuid;
        $zoneId = (string)$node->zoneId;
        $recordName = (string)$node->recordName;
        $recordType = (string)$node->recordType;
        $oldValue = (string)$node->oldValue;
        $oldTtl = (string)$node->oldTtl;
        $newValue = (string)$node->newValue;
        $newTtl = (string)$node->newTtl;

        // Get the account's API token
        $accountNode = $mdl->getNodeByReference('accounts.account.' . $accountUuid);
        if ($accountNode === null) {
            return ['status' => 'error', 'message' => 'Account not found - cannot revert'];
        }

        $token = (string)$accountNode->apiToken;
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'Account has no API token'];
        }

        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
        $backend = new Backend();
        $result = null;

        // Perform the reverse action
        if ($action === 'create') {
            // Revert create = delete the record
            $response = $backend->configdpRun('hclouddns dns delete', [
                $token, $zoneId, $recordName, $recordType
            ]);
            $result = json_decode(trim($response), true);
        } elseif ($action === 'delete') {
            // Revert delete = recreate the record with old values
            $ttl = !empty($oldTtl) ? $oldTtl : 300;
            $response = $backend->configdpRun('hclouddns dns create', [
                $token, $zoneId, $recordName, $recordType, $oldValue, $ttl
            ]);
            $result = json_decode(trim($response), true);
        } elseif ($action === 'update') {
            // Revert update = update back to old values
            $ttl = !empty($oldTtl) ? $oldTtl : 300;
            $response = $backend->configdpRun('hclouddns dns update', [
                $token, $zoneId, $recordName, $recordType, $oldValue, $ttl
            ]);
            $result = json_decode(trim($response), true);
        }

        if ($result !== null && isset($result['status']) && $result['status'] === 'ok') {
            // Mark the history entry as reverted
            $node->reverted = '1';
            $mdl->serializeToConfig();
            Config::getInstance()->save();

            return [
                'status' => 'ok',
                'message' => 'Change reverted successfully'
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Failed to revert change: ' . ($result['message'] ?? 'Unknown error')
        ];
    }

    /**
     * Clean up old history entries
     * @return array
     */
    public function cleanupAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $mdl = $this->getModel();
        $retentionDays = (int)$mdl->general->historyRetentionDays;
        $cutoffTime = time() - ($retentionDays * 86400);

        $deleted = 0;
        $toDelete = [];

        foreach ($mdl->history->change->iterateItems() as $uuid => $change) {
            $timestamp = (int)(string)$change->timestamp;
            if ($timestamp < $cutoffTime) {
                $toDelete[] = $uuid;
            }
        }

        foreach ($toDelete as $uuid) {
            $mdl->history->change->del($uuid);
            $deleted++;
        }

        if ($deleted > 0) {
            $mdl->serializeToConfig();
            Config::getInstance()->save();
        }

        return [
            'status' => 'ok',
            'deleted' => $deleted,
            'message' => "Cleaned up $deleted old history entries"
        ];
    }

    /**
     * Clear all history entries
     * @return array
     */
    public function clearAllAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $mdl = $this->getModel();
        $deleted = 0;
        $toDelete = [];

        foreach ($mdl->history->change->iterateItems() as $uuid => $change) {
            $toDelete[] = $uuid;
        }

        foreach ($toDelete as $uuid) {
            $mdl->history->change->del($uuid);
            $deleted++;
        }

        if ($deleted > 0) {
            $mdl->serializeToConfig();
            Config::getInstance()->save();
        }

        return [
            'status' => 'ok',
            'deleted' => $deleted,
            'message' => "Cleared all $deleted history entries"
        ];
    }

    /**
     * Add a history entry (internal use)
     * @param string $action create|update|delete
     * @param string $accountUuid
     * @param string $accountName
     * @param string $zoneId
     * @param string $zoneName
     * @param string $recordName
     * @param string $recordType
     * @param string $oldValue
     * @param int $oldTtl
     * @param string $newValue
     * @param int $newTtl
     * @return bool
     */
    public static function addEntry($action, $accountUuid, $accountName, $zoneId, $zoneName, $recordName, $recordType, $oldValue = '', $oldTtl = 0, $newValue = '', $newTtl = 0)
    {
        $mdl = new \OPNsense\HCloudDNS\HCloudDNS();

        $change = $mdl->history->change->Add();
        $change->timestamp = time();
        $change->action = $action;
        $change->accountUuid = $accountUuid;
        $change->accountName = $accountName;
        $change->zoneId = $zoneId;
        $change->zoneName = $zoneName;
        $change->recordName = $recordName;
        $change->recordType = $recordType;
        $change->oldValue = $oldValue;
        $change->oldTtl = $oldTtl;
        $change->newValue = $newValue;
        $change->newTtl = $newTtl;
        $change->reverted = '0';

        $mdl->serializeToConfig();
        Config::getInstance()->save();

        return true;
    }
}
