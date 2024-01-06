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
 * Common constants and functions for all ACME classes
 * @package OPNsense\AcmeClient
 */
abstract class LeCommon
{
    // Static acme.sh directories and files
    public const ACME_BASE_ACCOUNT_DIR = '/var/etc/acme-client/accounts';
    public const ACME_BASE_CERT_DIR = '/var/etc/acme-client/certs';
    public const ACME_BASE_CONFIG_DIR = '/var/etc/acme-client/configs';
    public const ACME_CMD = '/usr/local/sbin/acme.sh';
    public const ACME_HOME_DIR = '/var/etc/acme-client/home';

    // Defaults for acme.sh
    public const ACME_ACCOUNT_KEY_LENGTH = 4096;
    public const ACME_ENV_PATH = '/sbin:/bin:/usr/sbin:/usr/bin:/usr/games:/usr/local/sbin:/usr/local/bin';
    public const ACME_SCRIPT_HOME = '/usr/local/share/examples/acme.sh';
    public const ACME_WEBROOT = '/var/etc/acme-client/challenges';

    // Filenames for certs, configs, ...
    public const ACME_CERT_DIR = '/var/etc/acme-client/certs/%s/';
    public const ACME_CERT_FILE = '/var/etc/acme-client/certs/%s/cert.pem';
    public const ACME_CERT_HOME_DIR = '/var/etc/acme-client/cert-home/%s';
    public const ACME_CHAIN_FILE = '/var/etc/acme-client/certs/%s/chain.pem';
    public const ACME_CONFIG_DIR = '/var/etc/acme-client/configs/%s/';
    public const ACME_FULLCHAIN_FILE = '/var/etc/acme-client/certs/%s/fullchain.pem';
    public const ACME_KEY_DIR = '/var/etc/acme-client/keys/%s/';
    public const ACME_KEY_FILE = '/var/etc/acme-client/keys/%s/private.key';

    // acme.sh internals
    public const ACME_DEPLOY_HOOK_STRING = 'Le_DeployHook=';

    // Runtime parameters for acme.sh
    protected $acme_args = array(); # command line arguments to be passed to acme.sh
    protected $acme_env = array();  # environment variables to be used when running acme.sh
    protected $acme_keylength;      # private key length in acme.sh compatible format
    protected $acme_syslog;         # syslog log level

    // Certificate details and configuration
    protected $cert_id;             # AcmeClient certificate object ID
    protected $cert_name;           # certificate name
    protected $cert_altnames;       # certificate altNames
    protected $cert_aliasmode;      # AcmeClient certificate object aliasmode
    protected $cert_domainalias;    # AcmeClient certificate object domain alias
    protected $cert_challengealias; # AcmeClient certificate object challenge alias
    protected $cert_keylength;      # Private key length
    protected $cert_ecc;            # ECC cert?

    // Account details
    protected $account_id;          # AcmeClient account object ID
    protected $account_uuid;        # AcmeClient account object UUID

    // Automation details and configuration
    protected $command;             # configd command to run
    protected $command_args;        # optional args for configdRun()

    // Basic object information
    protected $cron;                # Run from cron job
    protected $config;              # AcmeClient config object
    protected $debug;               # Debug logging (bool)
    protected $ca;                  # ACME CA
    protected $custom_ca;           # Custom ACME CA URL
    protected $ca_compat;           # ACME CA for compat with old LE CA names
    protected $force;               # Force operation
    protected $model;               # AcmeClient model object
    protected $uuid;                # AcmeClient config object uuid
    protected $validation;          # LeValidation object

    /**
     * get ID from auxiliary configuration object
     * @return string
     */
    public function getId()
    {
        return (string)$this->config->id;
    }

    /**
     * get UUID from auxiliary configuration object
     * @return string
     */
    public function getUuid()
    {
        return (string)$this->uuid;
    }

    /**
     * load config object from configuration
     * @return bool
     */
    public function loadConfig(string $path, string $uuid)
    {
        // Get config object
        $model = new \OPNsense\AcmeClient\AcmeClient();
        $obj = $model->getNodeByReference("${path}.${uuid}");
        if ($obj == null) {
            LeUtils::log_error("config of type ${path} not found: ${uuid}");
            return false;
        }
        // Store config objects
        $this->config = $obj;
        $this->model = $model;
        $this->uuid = $uuid;
        return true;
    }

    /**
     * check if object is enabled in configuration
     * @return bool
     */
    public function isEnabled()
    {
        return (string)$this->config->enabled == 1 ? true : false;
    }

    /**
     * set ACME CA for acme.sh
     */
    public function setCa(string $uuid)
    {
        // Get account config object
        $model = new \OPNsense\AcmeClient\AcmeClient();
        $obj = $model->getNodeByReference("accounts.account.${uuid}");
        if (empty($obj) || $obj == null) {
            LeUtils::log_error("unable to set CA, account not found: ${uuid}");
            return false;
        }

        // Extract ACME CA from account config
        $acme_ca = (string)$obj->ca;
        $this->ca = $acme_ca;

        // Extract custom ACME CA URL
        $acme_custom_ca = (string)$obj->custom_ca;
        $this->custom_ca = $acme_custom_ca;

        // Add CA to acme arguments
        if ($acme_ca == "custom") {
            // Custom CA
            if (empty($acme_custom_ca) || ($acme_custom_ca == null)) {
                LeUtils::log_error("custom CA must not be empty.");
                return false;
            }
            $this->acme_args[] = LeUtils::execSafe('--server %s', $acme_custom_ca);
        } else {
            // Normal CAs
            $this->acme_args[] = LeUtils::execSafe('--server %s', $acme_ca);
        }

        // Evaluate how the CA should be represented in filenames.
        // This is a compatibility layer. It ensures that old files that
        // were generated for the Let's Encrypt Production/Staging CA
        // can still be used.
        switch ($acme_ca) {
            case 'letsencrypt':
                $ca_compat = 'prod';
                break;
            case 'letsencrypt_test':
                $ca_compat = 'stg';
                break;
            default:
                $ca_compat = $acme_ca;
                break;
        }
        $this->ca_compat = $ca_compat;
    }

    /**
     * set log level for acme.sh and configure optional debug logging
     */
    public function setLoglevel()
    {
        $loglevel = (string)$this->model->getNodeByReference('settings.logLevel');

        switch ($loglevel) {
            case 'extended':
                $this->acme_args[] = '--syslog 6';
                $this->acme_args[] = '--log-level 2';
                $this->acme_syslog = 6;
                $this->debug = false;
                break;
            case 'debug':
                $this->acme_args[] = '--syslog 7';
                $this->acme_args[] = '--debug';
                $this->acme_syslog = 7;
                $this->debug = true;
                break;
            case 'debug2':
                $this->acme_args[] = '--syslog 7';
                $this->acme_args[] = '--debug 2';
                $this->acme_syslog = 7;
                $this->debug = true;
                break;
            case 'debug3':
                $this->acme_args[] = '--syslog 7';
                $this->acme_args[] = '--debug 3';
                $this->acme_syslog = 7;
                $this->debug = true;
                break;
            default:
                $this->acme_args[] = '--syslog 6';
                $this->acme_args[] = '--log-level 1';
                $this->acme_syslog = 6;
                $this->debug = false;
                break;
        }
    }

    /**
     * update status information to reflect the result of the last operation
     * Supported status codes are:
     *   100     pending
     *   200     cert issued / acct registered
     *   250     cert revoked / acct deactivated
     *   300     configuration error
     *   400     issue/renew/registration failed
     *   500     internal error (code issues, bad luck, unexpected errors, ...)
     * Feel free to add more status codes to support new use-cases.
     * @return bool
     */
    public function setStatus(int $statusCode)
    {
        // Update attributes.
        $this->config->statusCode = $statusCode;
        $this->config->statusLastUpdate = time();

        // Serialize to config and save
        Config::getInstance()->unlock();
        $this->model->serializeToConfig();
        Config::getInstance()->save();

        // Reload to get most recent config
        Config::getInstance()->forceReload();
        $this->loadConfig($this::CONFIG_PATH, $this->uuid);

        return true;
    }

    /**
     * set UUID of auxiliary configuration object
     */
    public function setUuid(string $uuid)
    {
        $this->uuid = $uuid;
    }
}
