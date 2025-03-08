<?php

/*
 * Copyright (C) 2025 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Quagga\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M1_1_0 extends BaseModelMigration
{
    public function run($model)
    {
        $config = Config::getInstance()->object();

        if ($model->getNodeByReference('redistributions') === null) {
            $model->addChild('redistributions');
        }
        $redistributions = $model->getNodeByReference('redistributions.redistribution');

        // We migrate multiple models at the same time
        $protocols = ['bgp', 'ospf', 'ospf6'];

        foreach ($protocols as $protocol) {
            if (isset($config->OPNsense->quagga->{$protocol})) {
                $this->migrateRedistribute(
                    $redistributions,
                    $config->OPNsense->quagga->{$protocol},
                    $protocol
                );
            }
        }
    }

    private function migrateRedistribute($redistributions, $configNode, $protocol)
    {
        if (!$configNode || empty($configNode->redistribute)) {
            return;
        }

        $redistributeValues = explode(',', (string)$configNode->redistribute);
        $redistributemap = isset($configNode->redistributemap) ? (string)$configNode->redistributemap : '';

        if ($redistributions === null) {
            $redistributions = $model->addChild('redistributions');
        }

        // Collect existing redistribution values to prevent duplicates
        $existingRedistributions = [];
        foreach ($redistributions->iterateItems() as $existing) {
            if (!empty((string)$existing->redistribute)) {
                $existingRedistributions[] = (string)$existing->redistribute;
            }
        }

        foreach ($redistributeValues as $value) {
            $value = trim($value);
            if (empty($value) || in_array($value, $existingRedistributions, true)) {
                continue;
            }

            // Create a new redistribution entry
            $redistributionNode = $redistributions->add();
            $redistributionNode->enabled = '1';
            $redistributionNode->description = "Migrated route redistribution ($protocol)";
            $redistributionNode->redistribute = $value;
            $redistributionNode->linkedRoutemap = !empty($redistributemap) ? $redistributemap : '';
        }
    }

    // Model is saved by 'run_migrations.php'
}
