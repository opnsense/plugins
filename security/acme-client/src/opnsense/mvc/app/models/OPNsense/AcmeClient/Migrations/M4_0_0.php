<?php

/**
 *    Copyright (C) 2024 Frank Wall
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

class M4_0_0 extends BaseModelMigration
{
    public function run($model)
    {
        $acme_account_dir = '/var/etc/acme-client/accounts/';
        $old_acme_home = '/var/etc/acme-client/home/';
        $new_acme_home = '/var/etc/acme-client/cert-home/';

        // Remove CERT_HOME property from all account configs
        if (is_dir($acme_account_dir)) {
            $account_files = glob($acme_account_dir . '*/account.conf');
            foreach ($account_files as $account_file) {
                if (is_file($account_file)) {
                    // Parse config file and remove property
                    $account_conf = parse_ini_file($account_file);
                    if (isset($account_conf['CERT_HOME'])) {
                        unset($account_conf['CERT_HOME']);
                    }

                    // Convert array back to ini file format
                    $new_account_conf = array();
                    foreach ($account_conf as $key => $value) {
                        $new_account_conf[] = "${key}='${value}'";
                    }

                    // Write changes back to file
                    file_put_contents($account_file, implode("\n", $new_account_conf) . "\n");
                    chmod($account_file, 0600);
                }
            }
        }

        // Create new acme home directory
        if (!is_dir($new_acme_home)) {
            mkdir($new_acme_home, 0750);
        }

        // Migrate all certificates to new directory
        // OLD: /var/etc/acme-client/home/opnsense.example.com
        // NEW: /var/etc/acme-client/cert-home/659971be677b69.19708532/opnsense.example.com
        foreach ($model->getNodeByReference('certificates.certificate')->iterateItems() as $cert) {
            $cert_id = (string)$cert->id;
            $cert_name = (string)$cert->name;

            $old_cert_home = $old_acme_home . $cert_name;
            $new_cert_home = $new_acme_home . $cert_id . '/' . $cert_name;
            $old_cert_home_ecc = $old_acme_home . $cert_name . '_ecc';
            $new_cert_home_ecc = $new_acme_home . $cert_id . '/' . $cert_name . '_ecc';
            $_parent_dir = $new_acme_home . $cert_id;

            // Check if cert home directory exists
            // Certs that haven't been issued yet don't need to be migrated.
            if (is_dir($old_cert_home)) {
                // Create parent directory
                if (!is_dir($_parent_dir)) {
                    mkdir($_parent_dir, 0750);
                }
                // Rename cert home directory
                rename($old_cert_home, $new_cert_home);
            }

            // Migrate ECC certs
            if (is_dir($old_cert_home_ecc)) {
                // Create parent directory
                if (!is_dir($_parent_dir)) {
                    mkdir($_parent_dir, 0750);
                }
                // Rename cert home directory
                rename($old_cert_home_ecc, $new_cert_home_ecc);
            }
        }
    }
}
