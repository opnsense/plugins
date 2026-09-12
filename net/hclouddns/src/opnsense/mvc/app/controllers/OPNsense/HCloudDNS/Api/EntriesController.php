<?php

/**
 * Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
 * All rights reserved.
 */

namespace OPNsense\HCloudDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class EntriesController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\HCloudDNS\HCloudDNS';
    protected static $internalModelName = 'hclouddns';

    /**
     * Search entries with live status data
     * @return array search results
     */
    public function searchItemAction()
    {
        // Get base search results, default sort by account then recordName
        $result = $this->searchBase(
            'entries.entry',
            ['enabled', 'account', 'zoneName', 'recordName', 'recordType', 'primaryGateway', 'failoverGateway', 'currentIp', 'status', 'linkedEntry'],
            'account,recordName'
        );

        // Load live state data
        $stateFile = '/var/run/hclouddns_state.json';
        $state = [];
        if (file_exists($stateFile)) {
            $content = file_get_contents($stateFile);
            $state = json_decode($content, true) ?? [];
        }

        // Merge live data into results
        if (isset($result['rows']) && isset($state['entries'])) {
            foreach ($result['rows'] as &$row) {
                $uuid = $row['uuid'];
                if (isset($state['entries'][$uuid])) {
                    $entryState = $state['entries'][$uuid];
                    $row['currentIp'] = $entryState['hetznerIp'] ?? $row['currentIp'];
                    $row['status'] = $entryState['status'] ?? $row['status'];
                }
            }
            unset($row);
        }

        return $result;
    }

    /**
     * Get entry by UUID
     * @param string $uuid item unique id
     * @return array entry data
     */
    public function getItemAction($uuid = null)
    {
        return $this->getBase('entry', 'entries.entry', $uuid);
    }

    /**
     * Validate that failover gateway differs from primary
     * @return array|null error response or null if valid
     */
    private function validateGatewaySelection()
    {
        $entry = $this->request->getPost('entry');
        if (is_array($entry)) {
            $primary = $entry['primaryGateway'] ?? '';
            $failover = $entry['failoverGateway'] ?? '';
            if (!empty($primary) && !empty($failover) && $primary === $failover) {
                return [
                    'status' => 'error',
                    'validations' => [
                        'entry.failoverGateway' => 'Failover gateway must be different from primary gateway'
                    ]
                ];
            }
        }
        return null;
    }

    /**
     * Add new entry
     * @return array save result
     */
    public function addItemAction()
    {
        $validationError = $this->validateGatewaySelection();
        if ($validationError !== null) {
            return $validationError;
        }
        return $this->addBase('entry', 'entries.entry');
    }

    /**
     * Update entry
     * @param string $uuid item unique id
     * @return array save result
     */
    public function setItemAction($uuid)
    {
        $validationError = $this->validateGatewaySelection();
        if ($validationError !== null) {
            return $validationError;
        }
        return $this->setBase('entry', 'entries.entry', $uuid);
    }

    /**
     * Delete entry
     * @param string $uuid item unique id
     * @return array delete result
     */
    public function delItemAction($uuid)
    {
        return $this->delBase('entries.entry', $uuid);
    }

    /**
     * Toggle entry enabled status
     * If enabling an orphaned entry, recreate it at Hetzner first
     * @param string $uuid item unique id
     * @param string $enabled desired state (0/1), leave empty to toggle
     * @return array result
     */
    public function toggleItemAction($uuid, $enabled = null)
    {
        $mdl = $this->getModel();
        $node = $mdl->getNodeByReference('entries.entry.' . $uuid);

        if ($node === null) {
            return ['status' => 'error', 'message' => 'Entry not found'];
        }

        $currentEnabled = (string)$node->enabled;
        $currentStatus = (string)$node->status;
        $newEnabled = ($enabled !== null) ? $enabled : ($currentEnabled === '1' ? '0' : '1');

        // Check if enabling an orphaned entry - need to recreate at Hetzner first
        if ($newEnabled === '1' && $currentStatus === 'orphaned') {
            $accountUuid = (string)$node->account;
            $zoneId = (string)$node->zoneId;
            $recordName = (string)$node->recordName;
            $recordType = (string)$node->recordType;
            $ttl = (string)$node->ttl ?: '300';
            $primaryGateway = (string)$node->primaryGateway;

            // Get account token
            $accountNode = $mdl->getNodeByReference('accounts.account.' . $accountUuid);
            if ($accountNode === null) {
                return ['status' => 'error', 'message' => 'Account not found - cannot recreate record'];
            }

            $token = (string)$accountNode->apiToken;
            $apiType = (string)$accountNode->apiType ?: 'cloud';
            if (empty($token)) {
                return ['status' => 'error', 'message' => 'Account has no API token'];
            }

            // Get gateway IP
            $gwNode = $mdl->getNodeByReference('gateways.gateway.' . $primaryGateway);
            if ($gwNode === null) {
                return ['status' => 'error', 'message' => 'Primary gateway not found'];
            }

            // Use backend to get current gateway IP and create record
            $backend = new Backend();

            // Get gateway status to find IP
            $gwStatusResponse = $backend->configdRun('hclouddns gatewaystatus');
            $gwStatus = json_decode(trim($gwStatusResponse), true);
            $gwIp = '';
            if ($gwStatus && isset($gwStatus['gateways'][$primaryGateway])) {
                $gw = $gwStatus['gateways'][$primaryGateway];
                $gwIp = ($recordType === 'AAAA') ? ($gw['ipv6'] ?? '') : ($gw['ipv4'] ?? '');
            }

            if (empty($gwIp)) {
                return ['status' => 'error', 'message' => 'Could not get IP from gateway - is it online?'];
            }

            // Create record at Hetzner
            $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
            $response = $backend->configdpRun('hclouddns dns create', [
                $token, $zoneId, $recordName, $recordType, $gwIp, $ttl, $apiType
            ]);
            $result = json_decode(trim($response), true);

            if (!$result || $result['status'] !== 'ok') {
                $errMsg = $result['message'] ?? 'Unknown error';
                return ['status' => 'error', 'message' => "Failed to recreate record at Hetzner: $errMsg"];
            }

            // Update entry status to active and enable it
            $node->enabled = '1';
            $node->status = 'active';
            $node->currentIp = $gwIp;
            $mdl->serializeToConfig();
            \OPNsense\Core\Config::getInstance()->save();

            return [
                'status' => 'ok',
                'changed' => true,
                'message' => "Record recreated at Hetzner with IP $gwIp"
            ];
        }

        // Normal toggle for non-orphaned entries
        return $this->toggleBase('entries.entry', $uuid, $enabled);
    }

    /**
     * Pause/resume entry (sets status to paused/active)
     * @param string $uuid entry UUID
     * @return array result
     */
    public function pauseAction($uuid)
    {
        $result = ['status' => 'error', 'message' => 'Invalid entry'];

        if ($uuid !== null) {
            $mdl = $this->getModel();
            $node = $mdl->getNodeByReference('entries.entry.' . $uuid);
            if ($node !== null) {
                $currentStatus = (string)$node->status;
                if ($currentStatus === 'paused') {
                    $node->status = 'active';
                    $result = ['status' => 'ok', 'newStatus' => 'active'];
                } else {
                    $node->status = 'paused';
                    $result = ['status' => 'ok', 'newStatus' => 'paused'];
                }
                $mdl->serializeToConfig();
                \OPNsense\Core\Config::getInstance()->save();
            }
        }

        return $result;
    }

    /**
     * Batch add entries from zone selection
     * @return array result
     */
    /**
     * Check if an entry already exists
     * @param object $mdl the model
     * @param string $account account UUID
     * @param string $zoneId zone ID
     * @param string $recordName record name
     * @param string $recordType record type (A/AAAA)
     * @return bool true if entry exists
     */
    private function entryExists($mdl, $account, $zoneId, $recordName, $recordType)
    {
        foreach ($mdl->entries->entry->iterateItems() as $existing) {
            if ((string)$existing->account === $account &&
                (string)$existing->zoneId === $zoneId &&
                (string)$existing->recordName === $recordName &&
                (string)$existing->recordType === $recordType) {
                return true;
            }
        }
        return false;
    }

    public function batchAddAction()
    {
        $result = ['status' => 'error', 'message' => 'Invalid request'];

        if ($this->request->isPost()) {
            $entries = $this->request->getPost('entries');
            $primaryGateway = $this->request->getPost('primaryGateway');
            $failoverGateway = $this->request->getPost('failoverGateway');
            $ttl = $this->request->getPost('ttl', 'int', 300);

            if (is_array($entries) && count($entries) > 0) {
                // Validate failover differs from primary (only if both are set)
                if (!empty($primaryGateway) && !empty($failoverGateway) && $primaryGateway === $failoverGateway) {
                    return ['status' => 'error', 'message' => 'Failover gateway must be different from primary gateway'];
                }

                $mdl = $this->getModel();
                $added = 0;
                $skipped = 0;

                foreach ($entries as $entry) {
                    if (isset($entry['zoneId'], $entry['zoneName'], $entry['recordName'], $entry['recordType'])) {
                        $account = $entry['account'] ?? '';
                        // Skip if entry already exists (duplicate protection)
                        if ($this->entryExists($mdl, $account, $entry['zoneId'], $entry['recordName'], $entry['recordType'])) {
                            $skipped++;
                            continue;
                        }
                        $node = $mdl->entries->entry->Add();
                        $node->enabled = '1';
                        $node->account = $account;
                        $node->zoneId = $entry['zoneId'];
                        $node->zoneName = $entry['zoneName'];
                        $node->recordId = $entry['recordId'] ?? '';
                        $node->recordName = $entry['recordName'];
                        $node->recordType = $entry['recordType'];
                        $node->primaryGateway = $primaryGateway ?? '';
                        $node->failoverGateway = $failoverGateway ?? '';
                        // TTL is an OptionField with underscore prefix (_60, _300, etc.)
                        $ttlValue = $entry['ttl'] ?? $ttl;
                        $node->ttl = '_' . ltrim($ttlValue, '_');
                        $node->status = 'pending';
                        $added++;
                    }
                }

                if ($added > 0) {
                    $validationMessages = $mdl->performValidation();
                    if ($validationMessages->count() == 0) {
                        $mdl->serializeToConfig();
                        \OPNsense\Core\Config::getInstance()->save();
                        $result = ['status' => 'ok', 'added' => $added, 'skipped' => $skipped];
                    } else {
                        $errors = [];
                        foreach ($validationMessages as $msg) {
                            $errors[] = (string)$msg->getMessage();
                        }
                        $result = ['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors];
                    }
                } elseif ($skipped > 0) {
                    $result = ['status' => 'ok', 'added' => 0, 'skipped' => $skipped, 'message' => 'All selected entries already exist'];
                } else {
                    $result = ['status' => 'error', 'message' => 'No valid entries provided'];
                }
            }
        }

        return $result;
    }

    /**
     * Batch update entries (change gateway, pause, delete)
     * @return array result
     */
    public function batchUpdateAction()
    {
        $result = ['status' => 'error', 'message' => 'Invalid request'];

        if ($this->request->isPost()) {
            $uuids = $this->request->getPost('uuids');
            $action = $this->request->getPost('action');

            if (is_array($uuids) && !empty($action)) {
                $mdl = $this->getModel();
                $processed = 0;

                foreach ($uuids as $uuid) {
                    $node = $mdl->getNodeByReference('entries.entry.' . $uuid);
                    if ($node !== null) {
                        switch ($action) {
                            case 'pause':
                                $node->status = 'paused';
                                $processed++;
                                break;
                            case 'resume':
                                $node->status = 'active';
                                $processed++;
                                break;
                            case 'delete':
                                $mdl->entries->entry->del($uuid);
                                $processed++;
                                break;
                            case 'setGateway':
                                $gateway = $this->request->getPost('gateway');
                                if (!empty($gateway)) {
                                    $node->primaryGateway = $gateway;
                                    $processed++;
                                }
                                break;
                            case 'setFailover':
                                $failover = $this->request->getPost('failover');
                                $primary = (string)$node->primaryGateway;
                                // Validate failover differs from primary
                                if (!empty($failover) && $failover === $primary) {
                                    continue 2; // Skip this entry
                                }
                                $node->failoverGateway = $failover ?? '';
                                $processed++;
                                break;
                        }
                    }
                }

                if ($processed > 0) {
                    $mdl->serializeToConfig();
                    \OPNsense\Core\Config::getInstance()->save();
                    $result = ['status' => 'ok', 'processed' => $processed];
                } else {
                    $result = ['status' => 'error', 'message' => 'No entries processed'];
                }
            }
        }

        return $result;
    }

    /**
     * Get Hetzner IP for an entry (reads from Hetzner API)
     * @param string $uuid entry UUID
     * @return array IP information
     */
    public function getHetznerIpAction($uuid = null)
    {
        $result = ['status' => 'error', 'message' => 'Invalid entry'];

        if ($uuid !== null) {
            $mdl = $this->getModel();
            $node = $mdl->getNodeByReference('entries.entry.' . $uuid);
            if ($node !== null) {
                $backend = new Backend();
                $zoneId = (string)$node->zoneId;
                $recordName = (string)$node->recordName;
                $recordType = (string)$node->recordType;

                $response = $backend->configdpRun('hclouddns gethetznerip', [$zoneId, $recordName, $recordType]);
                $data = json_decode(trim($response), true);
                if ($data !== null) {
                    $result = $data;
                } else {
                    $result = ['status' => 'error', 'message' => 'Backend error'];
                }
            }
        }

        return $result;
    }

    /**
     * Refresh all entries status from Hetzner
     * Marks entries not found at Hetzner as 'orphaned' and disables them
     * @return array status
     */
    public function refreshStatusAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('hclouddns refreshstatus');
        $data = json_decode(trim($response), true);

        if ($data === null) {
            return ['status' => 'error', 'message' => 'Could not refresh status'];
        }

        // Process entries and mark orphaned ones
        $mdl = $this->getModel();
        $orphanedCount = 0;
        $syncedCount = 0;

        if (isset($data['entries']) && is_array($data['entries'])) {
            foreach ($data['entries'] as $entryStatus) {
                $uuid = $entryStatus['uuid'] ?? '';
                if (empty($uuid)) {
                    continue;
                }

                $node = $mdl->getNodeByReference('entries.entry.' . $uuid);
                if ($node === null) {
                    continue;
                }

                $currentStatus = (string)$node->status;

                // If record not found at Hetzner and not already orphaned/paused
                if ($entryStatus['status'] === 'not_found' && !in_array($currentStatus, ['orphaned', 'paused'])) {
                    $node->status = 'orphaned';
                    $node->enabled = '0';  // Disable orphaned entries
                    $node->currentIp = '';  // Clear current IP since it doesn't exist at Hetzner
                    $orphanedCount++;
                }
                // If record found at Hetzner and currently orphaned, update to active
                elseif ($entryStatus['status'] === 'found' && $currentStatus === 'orphaned') {
                    $node->status = 'active';
                    $node->currentIp = $entryStatus['hetznerIp'] ?? '';
                    $syncedCount++;
                }
                // Update current IP for found records
                elseif ($entryStatus['status'] === 'found' && !empty($entryStatus['hetznerIp'])) {
                    $node->currentIp = $entryStatus['hetznerIp'];
                    $syncedCount++;
                }
            }
        }

        // Save if changes were made
        if ($orphanedCount > 0 || $syncedCount > 0) {
            $mdl->serializeToConfig();
            \OPNsense\Core\Config::getInstance()->save();
        }

        // Also check errors for entries with missing accounts - mark them as orphaned too
        $accountMissingCount = 0;
        if (isset($data['errors']) && is_array($data['errors'])) {
            foreach ($data['errors'] as $errorEntry) {
                $uuid = $errorEntry['uuid'] ?? '';
                if (empty($uuid)) {
                    continue;
                }

                // Check if the error is about missing account/token
                $errorMsg = $errorEntry['error'] ?? '';
                if (strpos($errorMsg, 'No valid account') !== false || strpos($errorMsg, 'token') !== false) {
                    $node = $mdl->getNodeByReference('entries.entry.' . $uuid);
                    if ($node !== null) {
                        $currentStatus = (string)$node->status;
                        if (!in_array($currentStatus, ['orphaned', 'paused'])) {
                            $node->status = 'orphaned';
                            $node->enabled = '0';
                            $node->currentIp = '';
                            $accountMissingCount++;
                        }
                    }
                }
            }
        }

        // Save if changes were made
        if ($accountMissingCount > 0) {
            $mdl->serializeToConfig();
            \OPNsense\Core\Config::getInstance()->save();
            $orphanedCount += $accountMissingCount;
        }

        $data['orphanedCount'] = $orphanedCount;
        $data['syncedCount'] = $syncedCount;
        $data['accountMissingCount'] = $accountMissingCount;
        if ($orphanedCount > 0) {
            $msg = "$orphanedCount entries marked as orphaned";
            if ($accountMissingCount > 0) {
                $msg .= " ($accountMissingCount with missing account)";
            }
            $data['message'] = $msg;
        }

        return $data;
    }

    /**
     * Get entries with live status from runtime state
     * @return array entries with current IP and status
     */
    public function liveStatusAction()
    {
        $result = [
            'status' => 'ok',
            'entries' => [],
            'gateways' => []
        ];

        // Load runtime state
        $stateFile = '/var/run/hclouddns_state.json';
        $state = [];
        if (file_exists($stateFile)) {
            $content = file_get_contents($stateFile);
            $state = json_decode($content, true) ?? [];
        }

        // Get entries from model
        $mdl = $this->getModel();
        $entries = $mdl->entries->entry;

        foreach ($entries->iterateItems() as $uuid => $entry) {
            $entryState = $state['entries'][$uuid] ?? [];
            $gatewayUuid = (string)$entry->primaryGateway;
            $activeGateway = $entryState['activeGateway'] ?? $gatewayUuid;

            // Get gateway name
            $gatewayName = '';
            if (!empty($activeGateway)) {
                $gw = $mdl->getNodeByReference('gateways.gateway.' . $activeGateway);
                if ($gw !== null) {
                    $gatewayName = (string)$gw->name;
                }
            }

            $result['entries'][] = [
                'uuid' => $uuid,
                'enabled' => (string)$entry->enabled,
                'zoneName' => (string)$entry->zoneName,
                'recordName' => (string)$entry->recordName,
                'recordType' => (string)$entry->recordType,
                'primaryGateway' => $gatewayUuid,
                'failoverGateway' => (string)$entry->failoverGateway,
                'ttl' => (string)$entry->ttl,
                'currentIp' => $entryState['hetznerIp'] ?? '',
                'status' => $entryState['status'] ?? (string)$entry->status,
                'activeGateway' => $activeGateway,
                'activeGatewayName' => $gatewayName,
                'lastUpdate' => $entryState['lastUpdate'] ?? 0,
                'propagated' => $entryState['propagated'] ?? null
            ];
        }

        // Add gateway status
        $gateways = $mdl->gateways->gateway;
        foreach ($gateways->iterateItems() as $uuid => $gw) {
            $gwState = $state['gateways'][$uuid] ?? [];
            $result['gateways'][$uuid] = [
                'uuid' => $uuid,
                'name' => (string)$gw->name,
                'interface' => (string)$gw->interface,
                'status' => $gwState['status'] ?? 'unknown',
                'ipv4' => $gwState['ipv4'] ?? null,
                'ipv6' => $gwState['ipv6'] ?? null,
                'simulated' => $gwState['simulated'] ?? false
            ];
        }

        $result['lastUpdate'] = $state['lastUpdate'] ?? 0;

        return $result;
    }

    /**
     * Create dual-stack (A + AAAA) linked entries
     * @return array result with created UUIDs
     */
    public function createDualStackAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $data = $this->request->getPost('entry');
        if (!is_array($data)) {
            return ['status' => 'error', 'message' => 'Invalid entry data'];
        }

        // Required fields
        $required = ['account', 'zoneId', 'zoneName', 'recordName', 'primaryGateway'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['status' => 'error', 'message' => "Missing required field: $field"];
            }
        }

        // Check for IPv6 gateway
        $ipv6Gateway = $data['ipv6Gateway'] ?? '';
        if (empty($ipv6Gateway)) {
            return ['status' => 'error', 'message' => 'IPv6 gateway is required for dual-stack'];
        }

        $mdl = $this->getModel();

        // Create A record
        $aEntry = $mdl->entries->entry->Add();
        $aUuid = $aEntry->getAttributes()['uuid'];
        $aEntry->enabled = $data['enabled'] ?? '1';
        $aEntry->account = $data['account'];
        $aEntry->zoneId = $data['zoneId'];
        $aEntry->zoneName = $data['zoneName'];
        $aEntry->recordName = $data['recordName'];
        $aEntry->recordType = 'A';
        $aEntry->primaryGateway = $data['primaryGateway'];
        $aEntry->failoverGateway = $data['failoverGateway'] ?? '';
        $aEntry->ttl = $data['ttl'] ?? '300';
        $aEntry->status = 'pending';

        // Create AAAA record
        $aaaaEntry = $mdl->entries->entry->Add();
        $aaaaUuid = $aaaaEntry->getAttributes()['uuid'];
        $aaaaEntry->enabled = $data['enabled'] ?? '1';
        $aaaaEntry->account = $data['account'];
        $aaaaEntry->zoneId = $data['zoneId'];
        $aaaaEntry->zoneName = $data['zoneName'];
        $aaaaEntry->recordName = $data['recordName'];
        $aaaaEntry->recordType = 'AAAA';
        $aaaaEntry->primaryGateway = $ipv6Gateway;
        $aaaaEntry->failoverGateway = $data['ipv6FailoverGateway'] ?? '';
        $aaaaEntry->ttl = $data['ttl'] ?? '300';
        $aaaaEntry->status = 'pending';

        // Link them together
        $aEntry->linkedEntry = $aaaaUuid;
        $aaaaEntry->linkedEntry = $aUuid;

        // Validate
        $valMsgs = $mdl->performValidation();
        if ($valMsgs->count() > 0) {
            $errors = [];
            foreach ($valMsgs as $msg) {
                $errors[] = $msg->getField() . ': ' . $msg->getMessage();
            }
            return ['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors];
        }

        // Save
        $mdl->serializeToConfig();
        \OPNsense\Core\Config::getInstance()->save();

        return [
            'status' => 'ok',
            'aUuid' => $aUuid,
            'aaaaUuid' => $aaaaUuid,
            'message' => 'Dual-stack entries created successfully'
        ];
    }

    /**
     * Get linked entry info
     * @param string $uuid entry UUID
     * @return array linked entry information
     */
    public function getLinkedAction($uuid = null)
    {
        if (empty($uuid)) {
            return ['status' => 'error', 'message' => 'UUID required'];
        }

        $mdl = $this->getModel();
        $node = $mdl->getNodeByReference('entries.entry.' . $uuid);

        if ($node === null) {
            return ['status' => 'error', 'message' => 'Entry not found'];
        }

        $linkedUuid = (string)$node->linkedEntry;
        if (empty($linkedUuid)) {
            return ['status' => 'ok', 'hasLinked' => false];
        }

        $linkedNode = $mdl->getNodeByReference('entries.entry.' . $linkedUuid);
        if ($linkedNode === null) {
            return ['status' => 'ok', 'hasLinked' => false, 'linkedBroken' => true];
        }

        return [
            'status' => 'ok',
            'hasLinked' => true,
            'linkedUuid' => $linkedUuid,
            'linkedType' => (string)$linkedNode->recordType,
            'linkedEnabled' => (string)$linkedNode->enabled,
            'linkedStatus' => (string)$linkedNode->status
        ];
    }

    /**
     * Get existing entries for an account (for import duplicate detection)
     * @return array list of existing entry keys (zoneId:recordName:recordType)
     */
    public function getExistingForAccountAction()
    {
        $result = ['status' => 'ok', 'entries' => []];

        if ($this->request->isPost()) {
            $accountUuid = $this->request->getPost('account_uuid', 'string', '');

            if (!empty($accountUuid)) {
                $mdl = $this->getModel();
                foreach ($mdl->entries->entry->iterateItems() as $uuid => $entry) {
                    if ((string)$entry->account === $accountUuid) {
                        $result['entries'][] = [
                            'uuid' => $uuid,
                            'zoneId' => (string)$entry->zoneId,
                            'zoneName' => (string)$entry->zoneName,
                            'recordName' => (string)$entry->recordName,
                            'recordType' => (string)$entry->recordType
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Remove all orphaned entries
     * @return array result with count of removed entries
     */
    public function removeOrphanedAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $mdl = $this->getModel();
        $removed = [];
        $uuidsToRemove = [];

        // First pass: collect orphaned entry UUIDs
        foreach ($mdl->entries->entry->iterateItems() as $uuid => $entry) {
            if ((string)$entry->status === 'orphaned') {
                $uuidsToRemove[] = $uuid;
                $removed[] = [
                    'uuid' => $uuid,
                    'recordName' => (string)$entry->recordName,
                    'zoneName' => (string)$entry->zoneName,
                    'recordType' => (string)$entry->recordType
                ];
            }
        }

        if (empty($uuidsToRemove)) {
            return [
                'status' => 'ok',
                'message' => 'No orphaned entries found',
                'removedCount' => 0,
                'removed' => []
            ];
        }

        // Second pass: remove entries
        foreach ($uuidsToRemove as $uuid) {
            $mdl->entries->entry->del($uuid);
        }

        // Save changes
        $mdl->serializeToConfig();
        \OPNsense\Core\Config::getInstance()->save();

        return [
            'status' => 'ok',
            'message' => count($removed) . ' orphaned entries removed',
            'removedCount' => count($removed),
            'removed' => $removed
        ];
    }

    /**
     * Apply default TTL to all DynDNS entries
     * Updates both local config and Hetzner DNS records
     * @return array result with updated count
     */
    public function applyDefaultTtlAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $mdl = $this->getModel();

        // Get the default TTL from settings
        $defaultTtl = (string)$mdl->general->defaultTtl;
        // Remove underscore prefix if present (e.g. "_60" -> "60")
        if (strpos($defaultTtl, '_') === 0) {
            $defaultTtl = substr($defaultTtl, 1);
        }
        $ttl = intval($defaultTtl) ?: 60;

        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];
        $backend = new Backend();

        // Loop through all entries
        foreach ($mdl->entries->entry->iterateItems() as $uuid => $entry) {
            // Skip disabled entries
            if ((string)$entry->enabled !== '1') {
                $skipped++;
                continue;
            }

            // Get entry details
            $accountUuid = (string)$entry->account;

            if (empty($accountUuid)) {
                $skipped++;
                continue;
            }

            // Get account token
            $account = $mdl->getNodeByReference('accounts.account.' . $accountUuid);
            if ($account === null) {
                $skipped++;
                continue;
            }

            $token = (string)$account->apiToken;
            $zoneId = (string)$entry->zoneId;
            $recordName = (string)$entry->recordName;
            $recordType = (string)$entry->recordType;

            if (empty($token) || empty($zoneId) || empty($recordName)) {
                $skipped++;
                continue;
            }

            // Get current IP from state or entry
            $stateFile = '/var/run/hclouddns_state.json';
            $currentIp = (string)$entry->currentIp;
            if (file_exists($stateFile)) {
                $state = json_decode(file_get_contents($stateFile), true) ?? [];
                if (isset($state['entries'][$uuid]['hetznerIp'])) {
                    $currentIp = $state['entries'][$uuid]['hetznerIp'];
                }
            }

            if (empty($currentIp)) {
                $skipped++;
                continue;
            }

            // Sanitize inputs
            $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
            $zoneId = preg_replace('/[^a-zA-Z0-9_-]/', '', $zoneId);
            $recordName = preg_replace('/[^a-zA-Z0-9@._*-]/', '', $recordName);

            // Update at Hetzner
            $response = $backend->configdpRun('hclouddns dns update', [
                $token, $zoneId, $recordName, $recordType, $currentIp, $ttl
            ]);
            $data = json_decode(trim($response), true);

            if ($data !== null && isset($data['status']) && $data['status'] === 'ok') {
                // Update local entry TTL
                $entry->ttl = '_' . $ttl;
                $updated++;
            } else {
                $failed++;
                $errorMsg = $data['message'] ?? 'Unknown error';
                $errors[] = "{$recordName}.{$entry->zoneName}: {$errorMsg}";
            }
        }

        // Save config changes
        if ($updated > 0) {
            $mdl->serializeToConfig();
            \OPNsense\Core\Config::getInstance()->save();
        }

        $message = "{$updated} entries updated to TTL {$ttl}s";
        if ($skipped > 0) {
            $message .= ", {$skipped} skipped";
        }
        if ($failed > 0) {
            $message .= ", {$failed} failed";
        }

        return [
            'status' => $failed === 0 ? 'ok' : 'partial',
            'message' => $message,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'ttl' => $ttl,
            'errors' => $errors
        ];
    }
}
