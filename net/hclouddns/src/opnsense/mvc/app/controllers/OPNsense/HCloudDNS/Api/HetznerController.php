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

/**
 * Class HetznerController
 * Proxy for Hetzner Cloud API calls (validate, list zones, list records)
 * @package OPNsense\HCloudDNS\Api
 */
class HetznerController extends ApiControllerBase
{
    /**
     * Validate API token
     * @return array
     */
    public function validateTokenAction()
    {
        $result = ['status' => 'error', 'valid' => false, 'message' => 'Invalid request'];

        if ($this->request->isPost()) {
            $token = $this->request->getPost('token', 'string', '');

            if (empty($token)) {
                return ['status' => 'error', 'valid' => false, 'message' => 'No token provided'];
            }

            // Sanitize token - only allow alphanumeric and common token characters
            $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);

            $backend = new Backend();
            $response = $backend->configdpRun('hclouddns validate', [$token]);
            $data = json_decode($response, true);

            if ($data !== null) {
                $result = [
                    'status' => $data['valid'] ? 'ok' : 'error',
                    'valid' => $data['valid'] ?? false,
                    'message' => $data['message'] ?? 'Unknown error',
                    'zone_count' => $data['zone_count'] ?? 0
                ];
            }
        }

        return $result;
    }

    /**
     * List zones for token
     * @return array
     */
    public function listZonesAction()
    {
        $result = ['status' => 'error', 'zones' => []];

        if ($this->request->isPost()) {
            $token = $this->request->getPost('token', 'string', '');

            if (empty($token)) {
                return ['status' => 'error', 'message' => 'No token provided', 'zones' => []];
            }

            $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);

            $backend = new Backend();
            $response = $backend->configdpRun('hclouddns list zones', [$token]);
            $data = json_decode($response, true);

            if ($data !== null && isset($data['zones'])) {
                $result = [
                    'status' => 'ok',
                    'zones' => $data['zones']
                ];
            } else {
                $result = ['status' => 'error', 'message' => $data['message'] ?? 'Failed to list zones', 'zones' => []];
            }
        }

        return $result;
    }

    /**
     * List zones for an existing account (by UUID)
     * @return array
     */
    public function listZonesForAccountAction()
    {
        $result = ['status' => 'error', 'zones' => []];

        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required', 'zones' => []];
        }

        $uuid = $this->request->getPost('account_uuid', 'string', '');
        if (empty($uuid)) {
            return ['status' => 'error', 'message' => 'Account UUID required', 'zones' => []];
        }

        // Load the model and get the account
        $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
        $node = $mdl->getNodeByReference('accounts.account.' . $uuid);

        if ($node === null) {
            return ['status' => 'error', 'message' => 'Account not found', 'zones' => []];
        }

        $token = (string)$node->apiToken;
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'Account has no API token', 'zones' => []];
        }

        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns list zones', [$token]);
        $data = json_decode($response, true);

        if ($data !== null && isset($data['zones'])) {
            $result = [
                'status' => 'ok',
                'zones' => $data['zones'],
                'accountUuid' => $uuid
            ];
        } else {
            $result = ['status' => 'error', 'message' => $data['message'] ?? 'Failed to list zones', 'zones' => []];
        }

        return $result;
    }

    /**
     * List records for zone using account UUID
     * @return array
     */
    public function listRecordsForAccountAction()
    {
        $result = ['status' => 'error', 'records' => []];

        if ($this->request->isPost()) {
            $accountUuid = $this->request->getPost('account_uuid', 'string', '');
            $zoneId = $this->request->getPost('zone_id', 'string', '');
            $allTypes = $this->request->getPost('all_types', 'string', '0');

            if (empty($accountUuid) || empty($zoneId)) {
                return ['status' => 'error', 'message' => 'Account UUID and zone_id required', 'records' => []];
            }

            // Load the model and get the account
            $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
            $node = $mdl->getNodeByReference('accounts.account.' . $accountUuid);

            if ($node === null) {
                return ['status' => 'error', 'message' => 'Account not found', 'records' => []];
            }

            $token = (string)$node->apiToken;
            if (empty($token)) {
                return ['status' => 'error', 'message' => 'Account has no API token', 'records' => []];
            }

            $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
            $zoneId = preg_replace('/[^a-zA-Z0-9_-]/', '', $zoneId);

            $backend = new Backend();
            // Use allrecords action if all_types is requested
            $action = ($allTypes === '1') ? 'hclouddns list allrecords' : 'hclouddns list records';
            $response = $backend->configdpRun($action, [$token, $zoneId]);
            $data = json_decode($response, true);

            if ($data !== null && isset($data['records'])) {
                $result = [
                    'status' => 'ok',
                    'records' => $data['records']
                ];
            }
        }

        return $result;
    }

    /**
     * List records for zone
     * @return array
     */
    public function listRecordsAction()
    {
        $result = ['status' => 'error', 'records' => []];

        if ($this->request->isPost()) {
            $token = $this->request->getPost('token', 'string', '');
            $zoneId = $this->request->getPost('zone_id', 'string', '');

            if (empty($token) || empty($zoneId)) {
                return ['status' => 'error', 'message' => 'Token and zone_id required', 'records' => []];
            }

            $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
            $zoneId = preg_replace('/[^a-zA-Z0-9_-]/', '', $zoneId);

            $backend = new Backend();
            $response = $backend->configdpRun('hclouddns list records', [$token, $zoneId]);
            $data = json_decode($response, true);

            if ($data !== null && isset($data['records'])) {
                $result = [
                    'status' => 'ok',
                    'records' => $data['records']
                ];
            }
        }

        return $result;
    }

    /**
     * Sanitize record value based on record type
     * @param string $value
     * @param string $recordType
     * @return string
     */
    private function sanitizeRecordValue($value, $recordType)
    {
        switch ($recordType) {
            case 'A':
                // IPv4 address
                return preg_replace('/[^0-9.]/', '', $value);
            case 'AAAA':
                // IPv6 address
                return preg_replace('/[^a-fA-F0-9:]/', '', $value);
            case 'CNAME':
            case 'NS':
            case 'PTR':
                // Hostname
                return preg_replace('/[^a-zA-Z0-9._-]/', '', $value);
            case 'MX':
                // Priority + hostname (e.g., "10 mail.example.com")
                return preg_replace('/[^a-zA-Z0-9._ -]/', '', $value);
            case 'TXT':
            case 'SPF':
                // Allow most printable ASCII for TXT records (SPF, DKIM, DMARC, etc.)
                // Remove only control characters and null bytes
                return preg_replace('/[\x00-\x1F\x7F]/', '', $value);
            case 'SRV':
                // Priority weight port target (e.g., "10 100 443 server.example.com")
                return preg_replace('/[^a-zA-Z0-9._ -]/', '', $value);
            case 'CAA':
                // Flags tag value (e.g., '0 issue "letsencrypt.org"')
                return preg_replace('/[^a-zA-Z0-9._ "\'-]/', '', $value);
            default:
                // Generic sanitization
                return preg_replace('/[^a-zA-Z0-9._:@" -]/', '', $value);
        }
    }

    /**
     * Create a new DNS zone at Hetzner
     * @return array
     */
    public function createZoneAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $accountUuid = $this->request->getPost('account_uuid', 'string', '');
        $zoneName = $this->request->getPost('zone_name', 'string', '');

        if (empty($accountUuid) || empty($zoneName)) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }

        $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
        $node = $mdl->getNodeByReference('accounts.account.' . $accountUuid);

        if ($node === null) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }

        $token = (string)$node->apiToken;
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'Account has no API token'];
        }

        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
        $zoneName = strtolower(preg_replace('/[^a-zA-Z0-9.-]/', '', $zoneName));

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns dns createzone', [$token, $zoneName]);
        $data = json_decode(trim($response), true);

        if ($data !== null && isset($data['status']) && $data['status'] === 'ok') {
            return [
                'status' => 'ok',
                'message' => $data['message'] ?? "Zone $zoneName created",
                'zone_id' => $data['zone_id'] ?? '',
                'zone_name' => $data['zone_name'] ?? $zoneName
            ];
        }

        return [
            'status' => 'error',
            'message' => $data['message'] ?? 'Failed to create zone'
        ];
    }

    /**
     * Create a new DNS record at Hetzner
     * @return array
     */
    public function createRecordAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $accountUuid = $this->request->getPost('account_uuid', 'string', '');
        $zoneId = $this->request->getPost('zone_id', 'string', '');
        $recordName = $this->request->getPost('record_name', 'string', '');
        $recordType = $this->request->getPost('record_type', 'string', 'A');
        $value = $this->request->getPost('value', 'string', '');
        $ttl = $this->request->getPost('ttl', 'int', 300);

        if (empty($accountUuid) || empty($zoneId) || empty($recordName) || empty($value)) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }

        // Load the model and get the account
        $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
        $node = $mdl->getNodeByReference('accounts.account.' . $accountUuid);

        if ($node === null) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }

        $token = (string)$node->apiToken;
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'Account has no API token'];
        }

        // Sanitize inputs
        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
        $zoneId = preg_replace('/[^a-zA-Z0-9_-]/', '', $zoneId);
        $recordName = preg_replace('/[^a-zA-Z0-9@._*-]/', '', $recordName);
        $recordType = strtoupper(preg_replace('/[^a-zA-Z]/', '', $recordType));
        $value = $this->sanitizeRecordValue($value, $recordType);
        $ttl = max(60, min(86400, intval($ttl)));

        // Get zone name for history
        $zoneName = $this->request->getPost('zone_name', 'string', '');
        if (empty($zoneName)) {
            $zoneName = $zoneId;
        }

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns dns create', [
            $token, $zoneId, $recordName, $recordType, $value, $ttl
        ]);
        $data = json_decode(trim($response), true);

        if ($data !== null && isset($data['status']) && $data['status'] === 'ok') {
            // Record history entry
            HistoryController::addEntry(
                'create',
                $accountUuid,
                (string)$node->name,
                $zoneId,
                $zoneName,
                $recordName,
                $recordType,
                '',
                0,
                $value,
                $ttl
            );
            return $data;
        }

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'error', 'message' => 'Failed to create record'];
    }

    /**
     * Update an existing DNS record at Hetzner
     * @return array
     */
    public function updateRecordAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $accountUuid = $this->request->getPost('account_uuid', 'string', '');
        $zoneId = $this->request->getPost('zone_id', 'string', '');
        $recordName = $this->request->getPost('record_name', 'string', '');
        $recordType = $this->request->getPost('record_type', 'string', 'A');
        $value = $this->request->getPost('value', 'string', '');
        $ttl = $this->request->getPost('ttl', 'int', 300);

        if (empty($accountUuid) || empty($zoneId) || empty($recordName) || empty($value)) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }

        // Load the model and get the account
        $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
        $node = $mdl->getNodeByReference('accounts.account.' . $accountUuid);

        if ($node === null) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }

        $token = (string)$node->apiToken;
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'Account has no API token'];
        }

        // Sanitize inputs
        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
        $zoneId = preg_replace('/[^a-zA-Z0-9_-]/', '', $zoneId);
        $recordName = preg_replace('/[^a-zA-Z0-9@._*-]/', '', $recordName);
        $recordType = strtoupper(preg_replace('/[^a-zA-Z]/', '', $recordType));
        $value = $this->sanitizeRecordValue($value, $recordType);
        $ttl = max(60, min(86400, intval($ttl)));

        // Get old values for history
        $oldValue = $this->request->getPost('old_value', 'string', '');
        $oldTtl = $this->request->getPost('old_ttl', 'int', 0);
        $zoneName = $this->request->getPost('zone_name', 'string', '');
        if (empty($zoneName)) {
            $zoneName = $zoneId;
        }

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns dns update', [
            $token, $zoneId, $recordName, $recordType, $value, $ttl
        ]);
        $data = json_decode(trim($response), true);

        if ($data !== null && isset($data['status']) && $data['status'] === 'ok') {
            // Record history entry
            HistoryController::addEntry(
                'update',
                $accountUuid,
                (string)$node->name,
                $zoneId,
                $zoneName,
                $recordName,
                $recordType,
                $oldValue,
                $oldTtl,
                $value,
                $ttl
            );
            return $data;
        }

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'error', 'message' => 'Failed to update record'];
    }

    /**
     * Delete a DNS record at Hetzner
     * @return array
     */
    public function deleteRecordAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $accountUuid = $this->request->getPost('account_uuid', 'string', '');
        $zoneId = $this->request->getPost('zone_id', 'string', '');
        $recordName = $this->request->getPost('record_name', 'string', '');
        $recordType = $this->request->getPost('record_type', 'string', 'A');

        if (empty($accountUuid) || empty($zoneId) || empty($recordName) || empty($recordType)) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }

        // Load the model and get the account
        $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
        $node = $mdl->getNodeByReference('accounts.account.' . $accountUuid);

        if ($node === null) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }

        $token = (string)$node->apiToken;
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'Account has no API token'];
        }

        // Sanitize inputs
        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
        $zoneId = preg_replace('/[^a-zA-Z0-9_-]/', '', $zoneId);
        $recordName = preg_replace('/[^a-zA-Z0-9@._*-]/', '', $recordName);
        $recordType = strtoupper(preg_replace('/[^a-zA-Z]/', '', $recordType));

        // Get old value and zone name for history
        $oldValue = $this->request->getPost('old_value', 'string', '');
        $oldTtl = $this->request->getPost('old_ttl', 'int', 0);
        $zoneName = $this->request->getPost('zone_name', 'string', '');
        if (empty($zoneName)) {
            $zoneName = $zoneId;
        }

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns dns delete', [
            $token, $zoneId, $recordName, $recordType
        ]);
        $data = json_decode(trim($response), true);

        if ($data !== null && isset($data['status']) && $data['status'] === 'ok') {
            // Record history entry
            HistoryController::addEntry(
                'delete',
                $accountUuid,
                (string)$node->name,
                $zoneId,
                $zoneName,
                $recordName,
                $recordType,
                $oldValue,
                $oldTtl,
                '',
                0
            );
            return $data;
        }

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'error', 'message' => 'Failed to delete record'];
    }

    /**
     * Export zone in BIND format
     * @return array
     */
    public function zoneExportAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $accountUuid = $this->request->getPost('account_uuid', 'string', '');
        $zoneId = $this->request->getPost('zone_id', 'string', '');

        if (empty($accountUuid) || empty($zoneId)) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }

        $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
        $node = $mdl->getNodeByReference('accounts.account.' . $accountUuid);
        if ($node === null) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }

        $token = (string)$node->apiToken;
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'Account has no API token'];
        }

        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
        $zoneId = preg_replace('/[^a-zA-Z0-9_-]/', '', $zoneId);

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns dns export', [$token, $zoneId]);
        $data = json_decode(trim($response), true);

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'error', 'message' => 'Export failed'];
    }

    /**
     * Parse imported zonefile
     * @return array
     */
    public function zoneImportParseAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $content = $this->request->getPost('content', 'string', '');
        if (empty($content)) {
            return ['status' => 'error', 'message' => 'No zonefile content provided'];
        }

        // Parse via Python script using stdin
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $cmd = '/usr/local/opnsense/scripts/HCloudDNS/zone_import.py';
        $process = proc_open($cmd, $descriptorspec, $pipes);

        if (is_resource($process)) {
            fwrite($pipes[0], $content);
            fclose($pipes[0]);

            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            $data = json_decode(trim($output), true);
            if ($data !== null) {
                return $data;
            }
        }

        return ['status' => 'error', 'message' => 'Import parse failed'];
    }

    /**
     * DNS Health Check for a zone
     * @return array
     */
    public function dnsHealthCheckAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $accountUuid = $this->request->getPost('account_uuid', 'string', '');
        $zoneId = $this->request->getPost('zone_id', 'string', '');
        $zoneName = $this->request->getPost('zone_name', 'string', '');

        if (empty($accountUuid) || empty($zoneId)) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }

        $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
        $node = $mdl->getNodeByReference('accounts.account.' . $accountUuid);
        if ($node === null) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }

        $token = (string)$node->apiToken;
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'Account has no API token'];
        }

        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
        $zoneId = preg_replace('/[^a-zA-Z0-9_-]/', '', $zoneId);
        $zoneName = preg_replace('/[^a-zA-Z0-9._-]/', '', $zoneName);

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns dns healthcheck', [
            $token, $zoneId, $zoneName
        ]);
        $data = json_decode(trim($response), true);

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'error', 'message' => 'Health check failed'];
    }

    /**
     * DNSSEC Status Check for a zone
     * @return array
     */
    public function dnssecStatusAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $accountUuid = $this->request->getPost('account_uuid', 'string', '');
        $zoneName = $this->request->getPost('zone_name', 'string', '');

        if (empty($accountUuid) || empty($zoneName)) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }

        $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
        $node = $mdl->getNodeByReference('accounts.account.' . $accountUuid);
        if ($node === null) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }

        $token = (string)$node->apiToken;
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'Account has no API token'];
        }

        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
        $zoneName = preg_replace('/[^a-zA-Z0-9._-]/', '', $zoneName);

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns dns dnssec', [
            $token, $zoneName
        ]);
        $data = json_decode(trim($response), true);

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'error', 'message' => 'DNSSEC check failed'];
    }

    /**
     * Zone Propagation Check
     * @return array
     */
    public function zonePropagationCheckAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $accountUuid = $this->request->getPost('account_uuid', 'string', '');
        $zoneId = $this->request->getPost('zone_id', 'string', '');

        if (empty($accountUuid) || empty($zoneId)) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }

        $mdl = new \OPNsense\HCloudDNS\HCloudDNS();
        $node = $mdl->getNodeByReference('accounts.account.' . $accountUuid);
        if ($node === null) {
            return ['status' => 'error', 'message' => 'Account not found'];
        }

        $token = (string)$node->apiToken;
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'Account has no API token'];
        }

        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
        $zoneId = preg_replace('/[^a-zA-Z0-9_-]/', '', $zoneId);

        $backend = new Backend();
        $response = $backend->configdpRun('hclouddns dns propagation', [
            $token, $zoneId
        ]);
        $data = json_decode(trim($response), true);

        if ($data !== null) {
            return $data;
        }

        return ['status' => 'error', 'message' => 'Propagation check failed'];
    }
}
