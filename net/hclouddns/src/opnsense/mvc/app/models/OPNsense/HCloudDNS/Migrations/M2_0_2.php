<?php

/**
 *    Copyright (c) 2025 Arcan Consulting (www.arcan-it.de)
 *    All rights reserved.
 */

namespace OPNsense\HCloudDNS\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M2_0_2 extends BaseModelMigration
{
    /**
     * Migrate to 2.0.2 - Convert TTL values to OptionField format
     * Old format: "300" (plain integer)
     * New format: "_300" (underscore-prefixed for XML element name)
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();

        // Check if our config section exists
        if (!isset($config->OPNsense->HCloudDNS->entries)) {
            return;
        }

        // Valid TTL values that need underscore prefix
        $validTtls = ['60', '120', '300', '600', '1800', '3600', '86400'];

        // Iterate over entries in the raw config
        foreach ($config->OPNsense->HCloudDNS->entries->children() as $entry) {
            if (isset($entry->ttl)) {
                $ttl = (string)$entry->ttl;
                // If TTL is plain number (not already prefixed), convert to underscore format
                if (in_array($ttl, $validTtls)) {
                    $entry->ttl = '_' . $ttl;
                }
            }
        }
    }
}
