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

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\HCloudDNS\HCloudDNS;

/**
 * Class HistoryController
 * @package OPNsense\HCloudDNS\Api
 */
class HistoryController extends ApiControllerBase
{
    private static $historyFile = '/var/log/hclouddns/history.jsonl';

    /**
     * Add a history entry (called from HetznerController after DNS changes)
     */
    public static function addEntry(
        $action,
        $accountUuid,
        $accountName,
        $zoneId,
        $zoneName,
        $recordName,
        $recordType,
        $oldValue,
        $oldTtl,
        $newValue,
        $newTtl
    ) {
        $dir = dirname(self::$historyFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $entry = [
            'uuid' => sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            ),
            'timestamp' => time(),
            'action' => $action,
            'accountUuid' => $accountUuid,
            'accountName' => $accountName,
            'zoneId' => $zoneId,
            'zoneName' => $zoneName,
            'recordName' => $recordName,
            'recordType' => $recordType,
            'oldValue' => $oldValue,
            'oldTtl' => intval($oldTtl),
            'newValue' => $newValue,
            'newTtl' => intval($newTtl),
            'reverted' => false
        ];

        $line = json_encode($entry) . "\n";
        $fp = @fopen(self::$historyFile, 'a');
        if ($fp) {
            flock($fp, LOCK_EX);
            fwrite($fp, $line);
            flock($fp, LOCK_UN);
            fclose($fp);
            @chmod(self::$historyFile, 0600);
        }
    }

    /**
     * Search history entries (from JSONL via configd)
     * @return array
     */
    public function searchItemAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('hclouddns history search');
        $data = json_decode(trim($response), true);

        if ($data !== null) {
            return $data;
        }

        return [
            'rows' => [],
            'rowCount' => 0,
            'total' => 0,
            'current' => 1
        ];
    }

    /**
     * Get a single history entry
     * @param string $uuid
     * @return array
     */
    public function getItemAction($uuid)
    {
        if (empty($uuid) || !preg_match('/^[a-f0-9-]{36}$/', $uuid)) {
            return ['status' => 'error', 'message' => 'Invalid UUID'];
        }

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns history get', [$uuid]);
        $data = json_decode(trim($response), true);

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'error', 'message' => 'History entry not found'];
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

        if (empty($uuid) || !preg_match('/^[a-f0-9-]{36}$/', $uuid)) {
            return ['status' => 'error', 'message' => 'Invalid UUID'];
        }

        // Get the history entry details
        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns history get', [$uuid]);
        $data = json_decode(trim($response), true);

        if ($data === null || $data['status'] !== 'ok' || !isset($data['change'])) {
            return ['status' => 'error', 'message' => 'History entry not found'];
        }

        $change = $data['change'];

        if ($change['reverted'] === '1') {
            return ['status' => 'error', 'message' => 'This change has already been reverted'];
        }

        $action = $change['action'];
        $accountUuid = $change['accountUuid'];
        $zoneId = $change['zoneId'];
        $recordName = $change['recordName'];
        $recordType = $change['recordType'];
        $oldValue = $change['oldValue'];
        $oldTtl = $change['oldTtl'] ?? '300';

        // Get the account's API token
        $mdl = new HCloudDNS();
        $accountNode = $mdl->getNodeByReference('accounts.account.' . $accountUuid);
        if ($accountNode === null) {
            return ['status' => 'error', 'message' => 'Account not found - cannot revert'];
        }

        $token = (string)$accountNode->apiToken;
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'Account has no API token'];
        }

        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
        $result = null;

        // Perform the reverse action
        if ($action === 'create') {
            $response = $backend->configdpRun('hclouddns dns delete', [
                $token, $zoneId, $recordName, $recordType
            ]);
            $result = json_decode(trim($response), true);
        } elseif ($action === 'delete') {
            $ttl = !empty($oldTtl) ? $oldTtl : 300;
            $response = $backend->configdpRun('hclouddns dns create', [
                $token, $zoneId, $recordName, $recordType, $oldValue, $ttl
            ]);
            $result = json_decode(trim($response), true);
        } elseif ($action === 'update') {
            $ttl = !empty($oldTtl) ? $oldTtl : 300;
            $response = $backend->configdpRun('hclouddns dns update', [
                $token, $zoneId, $recordName, $recordType, $oldValue, $ttl
            ]);
            $result = json_decode(trim($response), true);
        }

        if ($result !== null && isset($result['status']) && $result['status'] === 'ok') {
            // Mark the history entry as reverted via configd
            $backend->configdpRun('hclouddns history revert', [$uuid]);

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

        $mdl = new HCloudDNS();
        $retentionDays = (string)$mdl->general->historyRetentionDays ?: '7';

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns history cleanup', [$retentionDays]);
        $data = json_decode(trim($response), true);

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'error', 'message' => 'Cleanup failed'];
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

        $backend = new Backend();
        $response = $backend->configdRun('hclouddns history clear');
        $data = json_decode(trim($response), true);

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'error', 'message' => 'Clear failed'];
    }
}
