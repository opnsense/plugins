<?php

/*
 *    Copyright (C) 2024 Cedrik Pischem
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

namespace OPNsense\Caddy\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M1_1_3 extends BaseModelMigration
{
    public function run($model)
    {
        // Load the current system configuration
        $config = Config::getInstance()->object();

        // Ensure there are reverse proxy configurations to process
        if (!empty($config->Pischem->caddy->reverseproxy)) {

            // Loop through each reverse proxy configuration in the stored configuration config.xml
            foreach ($config->Pischem->caddy->reverseproxy->children() as $configNode) {

                // Extract the UUID attribute to identify the configuration item
                $uuid = (string)$configNode->attributes()->uuid;

                // Check if the current configuration item has a 'Description' to migrate
                if (!empty($configNode->Description)) {

                    // Store the value of 'Description' for migration
                    $descriptionValue = (string)$configNode->Description;

                    // Attempt to locate the corresponding node in the model using the UUID
                    $modelNode = null;

                    // Retrieve reverse proxy items from the model for matching UUID
                    $reverseProxies = $model->getNodeByReference('reverseproxy')->iterateItems();
                    foreach ($reverseProxies as $item) {
                        foreach ($item->iterateItems() as $modelUuid => $node) {
                            if ($uuid === $modelUuid) {
                                $modelNode = $node;
                                break 2; // Break from both loops once the node is found
                            }
                        }
                    }

                    // If a matching node is found in the model, migrate the 'Description' value to 'description' value
                    if ($modelNode !== null) {
                        $modelNode->description = $descriptionValue;
                    }
                }
            }
        }

        // Model is saved by 'run_migrations.php'
    }
}
