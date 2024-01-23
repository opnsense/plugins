<?php

/**
 *    Copyright (C) 2019 Frank Wall
 *
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
 *
 */

namespace OPNsense\AcmeClient\Migrations;

use OPNsense\Base\BaseModelMigration;

class M1_6_0 extends BaseModelMigration
{
    public function run($model)
    {
        // Get LE environment
        $env = (string)$model->settings->environment;
        $dir = '/var/etc/acme-client/accounts/';

        // Search accounts
        foreach ($model->getNodeByReference('accounts.account')->iterateItems() as $account) {
            $account_id = (string)$account->id;
            $account_dir = $dir . $account_id;
            $new_account_dir = "${dir}${account_id}_${env}";

            // Check if account directory exists
            // Accounts that haven't been used yet don't need to be migrated.
            if (is_dir($account_dir)) {
                // Check if account configuration can be found.
                $account_file = "${account_dir}/account.conf";
                if (is_file($account_file)) {
                    // Parse config file and modify path information
                    $account_conf = parse_ini_file($account_file);
                    foreach ($account_conf as $key => $value) {
                        switch ($key) {
                            case 'ACCOUNT_KEY_PATH':
                                $account_conf[$key] = "${new_account_dir}/account.key";
                                break;
                            case 'ACCOUNT_JSON_PATH':
                                $account_conf[$key] = "${new_account_dir}/account.json";
                                break;
                            case 'CA_CONF':
                                $account_conf[$key] = "${new_account_dir}/ca.conf";
                                break;
                        }
                    }

                    // Convert array back to ini file format
                    $new_account_conf = array();
                    foreach ($account_conf as $key => $value) {
                        $new_account_conf[] = "${key}='${value}'";
                    }

                    // Write changes back to file
                    file_put_contents($account_file, implode("\n", $new_account_conf) . "\n");
                    chmod($account_file, 0600);

                    // Finally, rename account directory
                    rename($account_dir, $new_account_dir);
                }
            }
        }
    }
}
