<?php

/*
 * Copyright (C) 2020-2024 Frank Wall
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

namespace OPNsense\AcmeClient;

use OPNsense\Core\Config;
use OPNsense\AcmeClient\LeUtils;

/**
 * Manage ACME CA accounts with acme.sh
 * @package OPNsense\AcmeClient
 */
class LeAccount extends LeCommon
{
    public const CONFIG_PATH = 'accounts.account';

    /*
     * create the object by collecting and storing all required data
     * @param $uuid string the UUID of the configuration object
     */
    public function __construct(string $uuid)
    {
        // Store basic information
        $this->uuid = $uuid;

        // Get config object
        $this->loadConfig(self::CONFIG_PATH, $this->uuid);

        // Set log level
        $this->setLoglevel();

        // Set ACME CA
        $this->setCa($uuid);

        // Store acme filenames
        $this->acme_args[] = LeUtils::execSafe('--home %s', self::ACME_HOME_DIR);
    }

    /**
     * generate private key and ACME config for this account
     */
    public function generateKey()
    {
        // Collect account information
        $account_conf_dir = self::ACME_BASE_ACCOUNT_DIR . '/' . (string)$this->config->id . '_' . $this->ca_compat;
        $account_conf_file = $account_conf_dir . '/account.conf';
        $account_key_file = $account_conf_dir . '/account.key';
        $account_json_file = $account_conf_dir . '/account.json';
        $account_ca_file = $account_conf_dir . '/ca.conf';
        $acme_conf = array();
        $acme_conf[] = "CERT_HOME='" . self::ACME_HOME_DIR . "'";
        $acme_conf[] = "SYS_LOG='" . $this->acme_syslog . "'";
        $acme_conf[] = "ACCOUNT_KEY_PATH='" . $account_key_file . "'";
        $acme_conf[] = "ACCOUNT_JSON_PATH='" . $account_json_file . "'";
        $acme_conf[] = "CA_CONF='" . $account_ca_file . "'";
        if (!empty((string)$this->config->email)) {
            $acme_conf[] = "ACCOUNT_EMAIL='" . (string)$this->config->email . "'";
        }

        // Store some values for later re-use
        $this->account_conf_file = $account_conf_file;

        // Create account configuration file
        if (!is_dir($account_conf_dir)) {
            mkdir($account_conf_dir, 0700, true);
        }
        file_put_contents($account_conf_file, (string)implode("\n", $acme_conf) . "\n");
        chmod($account_conf_file, 0600);

        // Check if account key already exists both in filesystem and in config
        if (!is_file($account_key_file) || empty((string)$this->config->key)) {
            LeUtils::log_debug('creating account key for ' . (string)$this->config->name, $this->debug);

            // Check if we have an account key in our configuration
            if (!empty((string)$this->config->key)) {
                LeUtils::log_debug('exporting existing account key to filesystem for ' . (string)$this->config->name, $this->debug);
                // Write key to disk
                file_put_contents($account_key_file, (string)base64_decode((string)$this->config->key));
                chmod($account_key_file, 0600);
                return true;
            } else {
                LeUtils::log_debug('generating a new account key for ' . (string)$this->config->name, $this->debug);

                // Preparation to run acme client
                $proc_env = $this->acme_env; // add env variables
                $proc_env['PATH'] = $this::ACME_ENV_PATH;

                // Prepare acme.sh command to generate a account key
                $acmecmd = '/usr/local/sbin/acme.sh '
                  . '--createAccountKey '
                  . implode(' ', $this->acme_args) . ' '
                  . LeUtils::execSafe('--accountkeylength %s', self::ACME_ACCOUNT_KEY_LENGTH) . ' '
                  . LeUtils::execSafe('--accountconf %s', $account_conf_file);
                LeUtils::log_debug('running acme.sh command: ' . (string)$acmecmd, $this->debug);

                // Run acme.sh command
                $result = LeUtils::run_shell_command($acmecmd, $proc_env);

                // Check acme.sh result
                if ($result) {
                    LeUtils::log_error('failed to create a new account key for ' . (string)$this->config->name);
                    $this->setStatus(300);
                    return false;
                }

                // Read account key file
                $account_key_content = @file_get_contents($account_key_file);
                if (empty($account_key_content) || ($account_key_content == false)) {
                    LeUtils::log_error("unable to read account key from file {$account_key_file}");
                    $this->setStatus(500);
                    return false;
                }

                // Reload to get most recent config
                Config::getInstance()->forceReload();
                $this->loadConfig(self::CONFIG_PATH, $this->uuid);

                // Import account key into config
                $this->config->key = base64_encode($account_key_content);

                // Serialize to config and save
                $this->model->serializeToConfig();
                Config::getInstance()->save();

                // Refresh config objects
                Config::getInstance()->forceReload();
                $this->loadConfig(self::CONFIG_PATH, $this->uuid);

                if (empty((string)$this->config->key)) {
                    $this->setStatus(500);
                    LeUtils::log_error('failed to save account key for ' . (string)$this->config->name);
                    return false;
                }
                LeUtils::log_debug('successfully created account key for ' . (string)$this->config->name, $this->debug);
                return true;
            }
        }
        return true;
    }

    /**
     * check if account is already registered
     * @return bool
     */
    public function isRegistered()
    {
        if (!empty((string)$this->config->statusLastUpdate) and !empty((string)$this->config->key) and ((string)$this->config->statusCode == '200')) {
            return true;
        }
        return false;
    }

    /**
     * register account with configured ACME CA
     * @return bool
     */
    public function register()
    {
        if (!($this->isEnabled())) {
            LeUtils::log('ignoring disabled account: ' . (string)$this->config->name);
            return false;
        }

        // Make sure a private already exists
        if (!($this->generateKey())) {
            LeUtils::log_error('aborting registration due to issues with account key: ' . (string)$this->config->name);
            return false;
        }

        // Check if account is already registered
        if (!($this->isRegistered())) {
            LeUtils::log_debug('starting account registration for ' . (string)$this->config->name, $this->debug);

            // Check if ACME External Account Binding (EAB) is enabled
            if (!empty((string)$this->config->eab_kid) && !empty((string)$this->config->eab_hmac)) {
                LeUtils::log_debug('enabling ACME EAB for this account', $this->debug);
                $this->acme_args[] = LeUtils::execSafe('--eab-kid %s', $this->config->eab_kid);
                $this->acme_args[] = LeUtils::execSafe('--eab-hmac-key %s', $this->config->eab_hmac);
            }

            // Preparation to run acme client
            $proc_env = $this->acme_env; // add env variables
            $proc_env['PATH'] = $this::ACME_ENV_PATH;

            // Prepare acme.sh command to register an account
            $acmecmd = '/usr/local/sbin/acme.sh '
              . '--registeraccount '
              . implode(' ', $this->acme_args) . ' '
              . LeUtils::execSafe('--accountconf %s', $this->account_conf_file);
            LeUtils::log_debug('running acme.sh command: ' . (string)$acmecmd, $this->debug);

            // Run acme.sh command
            $result = LeUtils::run_shell_command($acmecmd, $proc_env);

            // Check acme.sh result
            if ($result) {
                LeUtils::log_error('account registration failed for ' . $this->config->name);
                $this->setStatus(400);
                return false;
            }

            // Fix account config
            $this->fixConfig();

            // Update account status.
            LeUtils::log('account registration successful for ' . $this->config->name);
            $this->setStatus(200);
        } else {
            LeUtils::log_debug('account already registered: ' . (string)$this->config->name, $this->debug);
        }

        return true;
    }

    /**
     * Remove CERT_HOME property from account config,
     * otherwise --cert-home will be ignored by acme.sh.
     */
    public function fixConfig()
    {
        $account_conf_dir = self::ACME_BASE_ACCOUNT_DIR . '/' . (string)$this->config->id . '_' . $this->ca_compat;
        $account_conf_file = $account_conf_dir . '/account.conf';

        if (is_dir($account_conf_dir)) {
            if (is_file($account_conf_file)) {
                // Parse config file and remove property
                $account_conf = parse_ini_file($account_conf_file);
                if (isset($account_conf['CERT_HOME'])) {
                    unset($account_conf['CERT_HOME']);
                }

                // Convert array back to ini file format
                $new_account_conf = array();
                foreach ($account_conf as $key => $value) {
                    $new_account_conf[] = "{$key}='{$value}'";
                }

                // Write changes back to file
                file_put_contents($account_conf_file, implode("\n", $new_account_conf) . "\n");
                chmod($account_conf_file, 0600);
            }
        }
    }
}
