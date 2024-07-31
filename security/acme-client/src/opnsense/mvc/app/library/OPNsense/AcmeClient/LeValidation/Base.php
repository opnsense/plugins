<?php

/*
 * Copyright (C) 2020-2024 Frank Wall
 * Copyright (C) 2018 Deciso B.V.
 * Copyright (C) 2018 Franco Fichtner <franco@opnsense.org>
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

namespace OPNsense\AcmeClient\LeValidation;

use OPNsense\Core\Config;
use OPNsense\AcmeClient\LeAccount;
use OPNsense\AcmeClient\LeUtils;

/**
 * LeValidation stub file, contains shared logic for all validation methods.
 * @package OPNsense\AcmeClient
 */
abstract class Base extends \OPNsense\AcmeClient\LeCommon
{
    public const CONFIG_PATH = 'validations.validation';

    /**
     * The validation method cannot be properly initialized without the required
     * configuration. LeValidation returns a more or less uninitialized object
     * that first needs to be configured, and finally initialized by this function.
     * @param $certid string the ID of the certificate object
     * @param $accountuuid string the UUID of the account object
     * @return bool
     */
    public function init(string $certid, string $accountuuid, bool $certecc = false)
    {
        // Get config object
        $this->loadConfig(self::CONFIG_PATH, $this->uuid);

        // Get account object to query ID
        $account = new LeAccount($accountuuid);
        if (empty($account) || $account == null) {
            LeUtils::log_error("unable to load account information: {$accountuuid}");
            return false;
        }

        // Store auxiliary information (required to glue stuff together)
        $this->cert_id = $certid;
        $this->account_id = (string)$account->getId();
        $this->account_uuid = (string)$account->getUuid();

        // Teach acme.sh about DNS API hook location
        $this->acme_env['_SCRIPT_HOME'] = self::ACME_SCRIPT_HOME;

        // Set log level
        $this->setLoglevel();

        // Set ACME CA
        $this->setCa($accountuuid);

        // Store acme hook
        switch ((string)$this->config->method) {
            case 'dns01':
                $this->acme_args[] = LeUtils::execSafe('--dns %s', (string)$this->config->dns_service);
                if (! (string)$this->config->dns_sleep == '0') {
                    $this->acme_args[] = LeUtils::execSafe('--dnssleep %s', (string)$this->config->dns_sleep);
                }
                break;
            case 'http01':
                $this->acme_args[] = '--webroot ' . self::ACME_WEBROOT;
                break;
            case 'tlsalpn01':
                $this->acme_args[] = '--alpn';
                break;
        }

        // Store acme filenames
        $this->acme_args[] = LeUtils::execSafe('--home %s', self::ACME_HOME_DIR);
        $this->acme_args[] = LeUtils::execSafe('--cert-home %s', sprintf(self::ACME_CERT_HOME_DIR, $this->cert_id));
        $this->acme_args[] = LeUtils::execSafe('--certpath %s', sprintf(self::ACME_CERT_FILE, $this->cert_id));
        $this->acme_args[] = LeUtils::execSafe('--keypath %s', sprintf(self::ACME_KEY_FILE, $this->cert_id));
        $this->acme_args[] = LeUtils::execSafe('--capath %s', sprintf(self::ACME_CHAIN_FILE, $this->cert_id));
        $this->acme_args[] = LeUtils::execSafe('--fullchainpath %s', sprintf(self::ACME_FULLCHAIN_FILE, $this->cert_id));

        // ECC cert
        $this->cert_ecc = $certecc;

        return true;
    }

    /**
     * cleanup tasks that should run after performing the certificate validation
     * @return bool
     */
    public function cleanup()
    {
        // Dummy; no default cleanup tasks.
        return true;
    }

    /**
     * get the configured validation method (HTTP-01 or DNS-01)
     * @return string validation method
     */
    public function getMethod()
    {
        return $this->config->method;
    }

    /**
     * perform preparation tasks and run acme client
     * @param $renew optional parameter to specify if a renewal is required
     * @return bool
     */
    public function run(bool $renew = false)
    {
        if (!($this->isEnabled())) {
            LeUtils::log('ignoring disabled challenge type: ' . (string)$this->config->name);
            return false;
        }

        LeUtils::log('using challenge type: ' . (string)$this->config->name);

        // Issue or renew
        $acme_action = $renew == true ? 'renew' : 'issue';

        // Handle ECC certs
        if ($this->cert_ecc) {
            if ($renew == true) {
                // If it's a renew then pass --ecc to acme client to locate the correct cert directory
                $this->acme_args[] = '--ecc';
            }
        }

        // Use individual account config for each CA
        $account_conf_dir = self::ACME_BASE_ACCOUNT_DIR . '/' . $this->account_id . '_' . $this->ca_compat;
        $account_conf_file = $account_conf_dir . '/account.conf';

        // Preparation to run acme client
        $proc_env = $this->acme_env; // add env variables
        $proc_env['PATH'] = $this::ACME_ENV_PATH;

        // Prepare acme.sh command
        // NOTE: We "export" certificates to our own directory, so we don't have to deal
        // with domain names in filesystem, but instead can use the ID of our certObj, which
        // will never change.
        $acmecmd = self::ACME_CMD
          . ' '
          . "--{$acme_action} "
          . implode(' ', $this->acme_args) . ' '
          . LeUtils::execSafe('--accountconf %s', $account_conf_file);
        LeUtils::log_debug('running acme.sh command: ' . (string)$acmecmd, $this->debug);

        // Run acme.sh command
        $result = LeUtils::run_shell_command($acmecmd, $proc_env);

        // Run optional cleanup tasks.
        $this->cleanup();

        // Check acme.sh result
        if ($result) {
            LeUtils::log_error('domain validation failed (' . $this->getMethod() . ')');
            return false;
        }

        return true;
    }

    /**
     * add config to force certificate renewal
     * @param $force bool indicate whether force should be enabled or not
     */
    public function setForce(bool $force = false)
    {
        $this->acme_args[] = $force == true ? '--force' : null;
    }

    /**
     * set key length
     * @param $length key length
     */
    public function setKey(string $length = '4096')
    {
        if ($length == 'ec256' || $length == 'ec384') {
            $key_length = substr_replace($length, '-', 2, 0);
        } else {
            $key_length = $length;
        }

        $this->acme_args[] = LeUtils::execSafe('--keylength %s', $key_length);
        $this->cert_keylength = $length;
    }

    /**
     * configure certificate common name, altNames and DNS alias mode
     */
    public function setNames(string $certname, string $altnames = '', string $aliasmode = '', string $domainalias = '', string $challengealias = '')
    {
        // Store basic certificate information
        $this->cert_name = $certname;
        $this->cert_altnames = $altnames;
        $this->cert_aliasmode = $aliasmode;
        $this->cert_domainalias = $domainalias;
        $this->cert_challengealias = $challengealias;

        // Main domain for acme
        $this->acme_args[] = LeUtils::execSafe('--domain %s', $certname);

        // Main domain: Use DNS alias mode for domain validation?
        // https://github.com/acmesh-official/acme.sh/wiki/DNS-alias-mode
        if ($this->getMethod() == 'dns01') {
            switch ((string)$aliasmode) {
                case 'automatic':
                    $name = '_acme-challenge.' . ltrim((string)$this->cert_name, '*.');
                    if ($dst = dns_get_record($name, DNS_CNAME)) {
                        $this->acme_args[] = LeUtils::execSafe('--domain-alias %s', $dst[0]['target']);
                    }
                    break;
                case 'domain':
                    $this->acme_args[] = LeUtils::execSafe('--domain-alias %s', (string)$this->cert_domainalias);
                    break;
                case 'challenge':
                    $this->acme_args[] = LeUtils::execSafe('--challenge-alias %s', (string)$this->cert_challengealias);
                    break;
            }
        }

        // altNames
        if (!empty((string)$this->cert_altnames)) {
            foreach (explode(",", (string)$this->cert_altnames) as $altname) {
                $this->acme_args[] = LeUtils::execSafe('--domain %s', $altname);

                // altNames: Use DNS alias mode for domain validation?
                // https://github.com/acmesh-official/acme.sh/wiki/DNS-alias-mode
                if ($this->getMethod() == 'dns01') {
                    switch ((string)$this->cert_aliasmode) {
                        case 'automatic':
                            $name = "_acme-challenge." . ltrim($altname, '*.');
                            if ($dst = dns_get_record($name, DNS_CNAME)) {
                                $this->acme_args[] = LeUtils::execSafe('--domain-alias %s', $dst[0]['target']);
                            }
                            break;
                        case 'domain':
                            $this->acme_args[] = LeUtils::execSafe('--domain-alias %s', (string)$this->cert_domainalias);
                            break;
                        case 'challenge':
                            $this->acme_args[] = LeUtils::execSafe('--challenge-alias %s', (string)$this->cert_challengealias);
                            break;
                    }
                }
            }
        }
    }

    /**
     * enable OCSP extension
     * @param $ocsp bool whether ocsp extension should be enabled or not
     */
    public function setOcsp(bool $ocsp = false)
    {
        // if OCSP extension is turned on pass --ocsp parameter to acme client
        $this->acme_args[] = $ocsp == true ? '--ocsp' : null;
    }

    /**
     * set renewal interval
     * @param $interval int specifies the renewal interval in days
     */
    public function setRenewal(int $interval = 60)
    {
        $this->acme_args[] = LeUtils::execSafe('--days %s', (string)$interval);
    }
}
