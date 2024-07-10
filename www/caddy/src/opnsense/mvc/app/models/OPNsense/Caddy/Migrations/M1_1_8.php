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

// @codingStandardsIgnoreStart
class M1_1_8 extends BaseModelMigration
// @codingStandardsIgnoreEnd
{
    public function run($model)
    {
        // Load the current system configuration
        $config = Config::getInstance()->object();

        // Read and migrate TlsAutoHttps setting if necessary
        if (!empty($config->Pischem->caddy->general->TlsAutoHttps)) {
            $tlsAutoHttpsValue = (string)$config->Pischem->caddy->general->TlsAutoHttps;
            // Check if the current value is 'on' and needs to be migrated
            if ($tlsAutoHttpsValue === 'on') {
                // Locate the corresponding node in the model
                $modelNode = $model->getNodeByReference('general.TlsAutoHttps');
                if ($modelNode != null) {
                    // Set to empty value in the model, migration from 'on' to ''
                    $modelNode->setValue('');
                }
            }
        }

        // Read and migrate TlsDnsProvider setting if necessary
        if (!empty($config->Pischem->caddy->general->TlsDnsProvider)) {
            $tlsDnsProviderValue = (string)$config->Pischem->caddy->general->TlsDnsProvider;
            // Check if the current value is 'none' and needs to be migrated
            if ($tlsDnsProviderValue === 'none') {
                // Locate the corresponding node in the model
                $modelNode = $model->getNodeByReference('general.TlsDnsProvider');
                if ($modelNode != null) {
                    // Set to empty value in the model, migration from 'none' to ''
                    $modelNode->setValue('');
                }
            }
        }

        // Model is saved by 'run_migrations.php'
    }
}
