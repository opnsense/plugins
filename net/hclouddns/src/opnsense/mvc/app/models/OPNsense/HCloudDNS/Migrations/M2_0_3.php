<?php

/**
 *    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
 *    All rights reserved.
 */

namespace OPNsense\HCloudDNS\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M2_0_3 extends BaseModelMigration
{
    /**
     * Migrate to 2.0.3 - Add CARP awareness settings
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();

        if (!isset($config->OPNsense->HCloudDNS->general)) {
            return;
        }

        $general = $config->OPNsense->HCloudDNS->general;

        // Add carpAware field with default disabled
        if (!isset($general->carpAware)) {
            $general->addChild('carpAware', '0');
        }

        // Add carpVhid field (empty = monitor all CARP interfaces)
        if (!isset($general->carpVhid)) {
            $general->addChild('carpVhid', '');
        }
    }
}
