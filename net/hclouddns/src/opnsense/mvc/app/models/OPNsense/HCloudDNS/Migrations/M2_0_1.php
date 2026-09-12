<?php

/**
 *    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
 *    All rights reserved.
 */

namespace OPNsense\HCloudDNS\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M2_0_1 extends BaseModelMigration
{
    /**
     * Migrate to 2.0.1 - Add notifications section with defaults
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();
        $hcloud = $config->OPNsense->HCloudDNS;

        if ($hcloud && !isset($hcloud->notifications)) {
            // Add notifications section with defaults
            $hcloud->addChild('notifications');
            $hcloud->notifications->addChild('enabled', '0');
            $hcloud->notifications->addChild('notifyOnUpdate', '1');
            $hcloud->notifications->addChild('notifyOnFailover', '1');
            $hcloud->notifications->addChild('notifyOnFailback', '1');
            $hcloud->notifications->addChild('notifyOnError', '1');
            $hcloud->notifications->addChild('emailEnabled', '0');
            $hcloud->notifications->addChild('emailTo', '');
            $hcloud->notifications->addChild('webhookEnabled', '0');
            $hcloud->notifications->addChild('webhookUrl', '');
            $hcloud->notifications->addChild('webhookMethod', 'POST');
            $hcloud->notifications->addChild('ntfyEnabled', '0');
            $hcloud->notifications->addChild('ntfyServer', 'https://ntfy.sh');
            $hcloud->notifications->addChild('ntfyTopic', '');
            $hcloud->notifications->addChild('ntfyPriority', 'default');
        }
    }
}
