<?php

/**
 *    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
 *    All rights reserved.
 */

namespace OPNsense\HCloudDNS\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M2_1_0 extends BaseModelMigration
{
    /**
     * Migrate to 2.1.0:
     * - Export existing config.xml history entries to JSONL file
     * - Remove history section from config.xml
     * - Add webhookSecret default to notifications
     * - Remove deprecated apiLayer field from existing accounts
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();

        if (!isset($config->OPNsense->HCloudDNS)) {
            return;
        }

        $hcloud = $config->OPNsense->HCloudDNS;

        // 1. Export existing history entries to JSONL before removing them
        if (isset($hcloud->history) && isset($hcloud->history->change)) {
            $historyDir = '/var/log/hclouddns';
            $historyFile = $historyDir . '/history.jsonl';

            if (!is_dir($historyDir)) {
                @mkdir($historyDir, 0700, true);
            }

            $entries = [];
            foreach ($hcloud->history->children() as $change) {
                if ($change->getName() !== 'change') {
                    continue;
                }
                $uuid = (string)$change->attributes()['uuid'] ?? '';
                if (empty($uuid)) {
                    $uuid = sprintf(
                        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000,
                        mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                    );
                }

                $entry = [
                    'uuid' => $uuid,
                    'timestamp' => (int)(string)$change->timestamp,
                    'action' => (string)$change->action,
                    'accountUuid' => (string)$change->accountUuid,
                    'accountName' => (string)$change->accountName,
                    'zoneId' => (string)$change->zoneId,
                    'zoneName' => (string)$change->zoneName,
                    'recordName' => (string)$change->recordName,
                    'recordType' => (string)$change->recordType,
                    'oldValue' => (string)$change->oldValue,
                    'oldTtl' => (int)(string)$change->oldTtl,
                    'newValue' => (string)$change->newValue,
                    'newTtl' => (int)(string)$change->newTtl,
                    'reverted' => ((string)$change->reverted === '1')
                ];
                $entries[] = $entry;
            }

            if (!empty($entries)) {
                $jsonlContent = '';
                foreach ($entries as $entry) {
                    $jsonlContent .= json_encode($entry) . "\n";
                }
                file_put_contents($historyFile, $jsonlContent);
                chmod($historyFile, 0600);
            }

            // Remove history section from config
            unset($hcloud->history);
        }

        // 2. Add webhookSecret default to notifications
        if (isset($hcloud->notifications)) {
            if (!isset($hcloud->notifications->webhookSecret)) {
                $hcloud->notifications->addChild('webhookSecret', '');
            }
        }

        // 3. Remove deprecated apiLayer field from existing accounts
        if (isset($hcloud->accounts)) {
            foreach ($hcloud->accounts->children() as $account) {
                if ($account->getName() !== 'account') {
                    continue;
                }
                if (isset($account->apiLayer)) {
                    unset($account->apiLayer);
                }
            }
        }
    }
}
