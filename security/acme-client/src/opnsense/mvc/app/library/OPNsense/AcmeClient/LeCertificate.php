<?php

/*
 * Copyright (C) 2020-2025 Frank Wall
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

require_once("script/load_phalcon.php");

use OPNsense\Core\Config;
use OPNsense\Trust\Ca;
use OPNsense\Trust\Cert;
use OPNsense\Trust\Store as CertStore;
use OPNsense\AcmeClient\LeAccount;
use OPNsense\AcmeClient\LeAutomationFactory;
use OPNsense\AcmeClient\LeValidationFactory;
use OPNsense\AcmeClient\LeUtils;

/**
 * Manage ACME certificates with acme.sh
 * @package OPNsense\AcmeClient
 */
class LeCertificate extends LeCommon
{
    public const CONFIG_PATH = 'certificates.certificate';

    /*
     * Create the object by collecting and storing all required data
     * @param $uuid string the UUID of the configuration object
     * @param $force bool whether to enforce issue/renewal of the cert
     * @param $cron bool run from cron job
     */
    public function __construct(string $uuid, bool $force = false, bool $cron = false)
    {
        // Store basic information
        $this->uuid = $uuid;
        $this->force = $force;
        $this->cron = $cron;

        // Get config object
        $this->loadConfig(self::CONFIG_PATH, $this->uuid);

        // Get account object to query ID
        $account = new LeAccount((string)$this->config->account);
        if (empty($account) || $account == null) {
            LeUtils::log_error('unable to load account information: ' . (string)$this->config->account);
            return false;
        }

        // Store auxiliary information (required to glue stuff together)
        $this->account_id = (string)$account->getId();
        $this->account_uuid = (string)$account->getUuid();

        // Set log level
        $this->setLoglevel();

        // Set ACME CA
        $this->setCa((string)$this->config->account);

        // Handle special key types
        if ($this->config->keyLength == 'key_ec256' || $this->config->keyLength == 'key_ec384') {
            // Pass --ecc to acme client to locate the correct cert directory
            $this->acme_args[] = '--ecc';
            $this->cert_ecc = true;
        } else {
            $this->cert_ecc = false;
        }

        // Store cert filenames
        $this->cert_file = (string)sprintf(self::ACME_CERT_FILE, $this->config->id);
        $this->cert_key_file = (string)sprintf(self::ACME_KEY_FILE, $this->config->id);
        $this->cert_chain_file = (string)sprintf(self::ACME_CHAIN_FILE, $this->config->id);
        $this->cert_fullchain_file = (string)sprintf(self::ACME_FULLCHAIN_FILE, $this->config->id);

        // Store acme filenames
        $this->acme_args[] = LeUtils::execSafe('--home %s', self::ACME_HOME_DIR);
        $this->acme_args[] = LeUtils::execSafe('--cert-home %s', sprintf(self::ACME_CERT_HOME_DIR, $this->config->id));
        $this->acme_args[] = LeUtils::execSafe('--certpath %s', $this->cert_file);
        $this->acme_args[] = LeUtils::execSafe('--keypath %s', $this->cert_key_file);
        $this->acme_args[] = LeUtils::execSafe('--capath %s', $this->cert_chain_file);
        $this->acme_args[] = LeUtils::execSafe('--fullchainpath %s', $this->cert_fullchain_file);
    }

    /**
     * Split PEM encoded data into a list of PEM encoded certificates.
     * @param string $pem PEM encoded certificates
     * @return array list of PEM encoded certificates
     */
    public static function splitCaChain(string $pem)
    {
        // Keep certificate sections only, the split result also contains the data between them
        return array_values(array_filter(
            CertStore::splitCerts($pem),
            fn($item) => str_starts_with($item, '-----BEGIN CERTIFICATE-----')
        ));
    }

    /**
     * Check if a CA item was imported by the ACME Client.
     * @param mixed $ca_item CA item
     * @return bool
     */
    public static function isAcmeCa(mixed $ca_item)
    {
        return str_ends_with((string)$ca_item->descr, self::ACME_TRUST_DESCR_SUFFIX);
    }

    /**
     * Compare subject to issuer (subject fits within issuer DN).
     * @param array $subject to find
     * @param array $issuer to match on
     * @return bool
     */
    private static function compareIssuer(array $subject, array $issuer): bool
    {
        return empty(array_diff(
            array_map('serialize', $subject),
            array_map('serialize', $issuer)
        ));
    }

    /**
     * Store each certificate of a CA chain as a separate CA item and link the
     * items to their issuers via caref.
     * Existing CA items are matched by subject and issuer of their first certificate.
     * The certificate of a matching item is replaced only when the item was
     * imported by the ACME Client and contains more than one certificate.
     * @param Ca $caModel CA model to update
     * @param string $chain PEM encoded CA certificates, issuer of the end-entity certificate first
     * @param mixed $first_item optional CA item that stores the first certificate of the chain
     * @return string|bool refid of the CA item storing the first certificate, false on error
     */
    public static function importCaChain(Ca $caModel, string $chain, mixed $first_item = null)
    {
        // Parse all certificates before the model is modified
        $chain_certs = [];
        foreach (self::splitCaChain($chain) as $ca_content) {
            $ca_details = CertStore::parseX509($ca_content);
            $ca_x509 = openssl_x509_parse($ca_content);
            if ($ca_details === false || $ca_x509 === false) {
                return false;
            }
            $chain_certs[] = ['content' => $ca_content, 'details' => $ca_details, 'x509' => $ca_x509];
        }
        if (empty($chain_certs)) {
            return false;
        }

        // Find or create one CA item per certificate
        $chain_items = [];
        foreach ($chain_certs as $idx => $chain_cert) {
            $ca_content = $chain_cert['content'];
            $ca_details = $chain_cert['details'];
            $ca_x509 = $chain_cert['x509'];
            $ca_subject = $ca_details['name'];
            $ca_cn = $ca_details['commonname'] ?? '';
            $ca_issuer = implode(",", $ca_details['issuer'] ?? []);
            $ca_descr = (string)$ca_cn . self::ACME_TRUST_DESCR_SUFFIX;

            $ca_item = null;
            if ($idx == 0 && $first_item !== null) {
                $ca_item = $first_item;
            } else {
                // Check if CA was previously imported
                foreach ($caModel->ca->iterateItems() as $cacrt) {
                    $cacrt_details = CertStore::parseX509(base64_decode((string)$cacrt->crt));
                    if ($cacrt_details === false) {
                        continue;
                    }
                    $cacrt_subject = $cacrt_details['name'];
                    $cacrt_issuer = implode(",", $cacrt_details['issuer'] ?? []);
                    if (($ca_subject === $cacrt_subject) and ($ca_issuer === $cacrt_issuer)) {
                        $ca_item = $cacrt;
                        break;
                    }
                }
            }

            if ($ca_item === null) {
                // Create new CA
                $ca_item = $caModel->ca->Add();
                $ca_item->refid = uniqid();
                $ca_item->descr = $ca_descr;
                $ca_item->crt = base64_encode($ca_content);
                LeUtils::log("imported ACME CA: {$ca_cn} ({$ca_item->refid})");
            } else {
                // Update existing CA
                if (
                    self::isAcmeCa($ca_item) &&
                    count(self::splitCaChain(base64_decode((string)$ca_item->crt))) > 1
                ) {
                    $ca_item->crt = base64_encode($ca_content);
                    LeUtils::log("updated ACME CA: {$ca_cn} ({$ca_item->refid})");
                }
                if ($idx == 0) {
                    $ca_item->descr = $ca_descr;
                }
            }
            $chain_items[] = ['item' => $ca_item, 'x509' => $ca_x509];
        }

        // Link each CA item to its issuer from the same chain
        foreach ($chain_items as $child_idx => $child) {
            if ($child['x509']['issuer'] == $child['x509']['subject']) {
                // self-signed
                continue;
            }
            $issuer_refid = null;
            foreach ($chain_items as $parent_idx => $parent) {
                if (
                    $parent_idx != $child_idx &&
                    self::compareIssuer($parent['x509']['subject'], $child['x509']['issuer'])
                ) {
                    $issuer_refid = (string)$parent['item']->refid;
                    break;
                }
            }
            if ($issuer_refid !== null) {
                $child['item']->caref = $issuer_refid;
            }
        }

        return (string)$chain_items[0]['item']->refid;
    }

    /**
     * Import the certificate into OPNsense's trust storage.
     * @param bool $skip_validation try to import even if some checks fail
     * @return bool
     */
    public function import(bool $skip_validation = false)
    {
        if (!($this->isEnabled())) {
            LeUtils::log("ignoring disabled certificate: " . (string)$this->config->name);
            return false;
        }

        // Cannot import if certificate was not issued yet.
        // NOTE: When the certificate was just issued, then the cert status
        // does not reflect this yet and must be ignored by setting $skip_validation.
        if (!($this->isIssued()) && !($skip_validation)) {
            LeUtils::log('ignoring import request for certificate ' . (string)$this->config->name . ' (not issued or revoked)');
            return false;
        }

        // Reload to get most recent config
        Config::getInstance()->forceReload();
        $this->loadConfig(self::CONFIG_PATH, $this->uuid);

        // Check if certificate files can be found
        clearstatcache(); // don't let the cache fool us
        foreach (array($this->cert_file, $this->cert_key_file, $this->cert_chain_file, $this->cert_fullchain_file) as $file) {
            if (!is_file($file)) {
                LeUtils::log_error("unable to import certificate " . $this->config->name . ", file not found: {$file}");
                Config::getInstance()->unlock();
                return false;
            }
        }

        /**
         * Step 1: import CA
         */

        // Read contents from CA file
        $ca_content = @file_get_contents($this->cert_chain_file);
        if ($ca_content == false) {
            LeUtils::log_error('unable to read CA certificate content from file');
            Config::getInstance()->unlock();
            return false;
        }

        // Store each CA certificate of the chain as a separate CA item
        $caModel = new Ca();
        $ca = array();
        $ca['refid'] = self::importCaChain($caModel, $ca_content);
        if ($ca['refid'] === false) {
            LeUtils::log_error('unable to import CA certificate chain for ' . (string)$this->config->name);
            Config::getInstance()->unlock();
            return false;
        }

        // Serialize to config and save
        // Skip validation because the current in-memory model may not
        // know about the CA items that were just created and linked.
        $caModel->serializeToConfig(false, true);
        Config::getInstance()->save();

        /**
         * Step 2: import certificate
         */

        // Read contents from certificate file
        $cert_content = @file_get_contents($this->cert_file);
        if ($cert_content != false) {
            $cert_details = CertStore::parseX509($cert_content);
            $cert_subject = $cert_details['name'];
            $cert_serial  = $cert_details['serialNumber'];
            $cert_issuer  = implode(",", $cert_details['issuer']);
        } else {
            LeUtils::log_error('unable to read certificate content from file');
            Config::getInstance()->unlock();
            $this->setStatus(500);
            return false;
        }

        // Read private key
        $key_content = @file_get_contents($this->cert_key_file);
        if ($key_content == false) {
            LeUtils::log_error('unable to read private key from file: ' . $this->cert_key_file);
            Config::getInstance()->unlock();
            $this->setStatus(500);
            return false;
        }

        // Prepare certificate
        $certModel = new Cert();
        $cert = array();
        $cert['refid'] = uniqid();
        $cert['caref'] = (string)$ca['refid'];
        $cert['descr'] = (string)$this->config->name . self::ACME_TRUST_DESCR_SUFFIX;
        $import_log_message = 'imported';
        $cert_found = false;

        // Check if cert was previously imported.
        // Otherwise just import as new cert.
        if (!empty((string)$this->config->certRefId)) {
            // Check if the previously imported certificate can still be found
            foreach ($certModel->cert->iterateItems() as $cfgCert) {
                // Check if IDs match
                if ((string)$this->config->certRefId == (string)$cfgCert->refid) {
                    // Use old refid instead of generating a new one
                    $cert['refid'] = (string)$cfgCert->refid;
                    $import_log_message = 'updated';
                    $cert_found = true;
                    break;
                }
            }
        }

        // Check if cert was found in config
        if ($cert_found == true) {
            // Update existing cert
            foreach ($certModel->cert->iterateItems() as $cfgCert) {
                if ((string)$cfgCert->refid == $cert['refid']) {
                    $cfgCert->descr = $cert['descr'];
                    $cfgCert->crt = base64_encode($cert_content);
                    $cfgCert->prv = base64_encode($key_content);
                    // Update CA ref, because it may be signed by a different CA.
                    $cfgCert->caref = $cert['caref'];
                    break;
                }
            }
        } else {
            // Create new cert
            $newcert = $certModel->cert->Add();
            foreach (array_keys($cert) as $certcfg) {
                $newcert->$certcfg = (string)$cert[$certcfg];
            }
            $newcert->crt = base64_encode($cert_content);
            $newcert->prv = base64_encode($key_content);
        }
        LeUtils::log("{$import_log_message} ACME X.509 certificate: {$this->config->name} ({$cert['refid']})");

        // Serialize to config and save
        // Skip validation because the current in-memory model may not
        // know about the CA item that was just created.
        $certModel->serializeToConfig(false, true);
        Config::getInstance()->save();

        /**
         * Step 3: update configuration
         */

        // Update Acme cert config
        $this->config->certRefId = $cert['refid'];
        $this->config->lastUpdate = time();

        // Serialize to config and save
        $this->model->serializeToConfig();
        Config::getInstance()->save();
        Config::getInstance()->forceReload();
        $this->loadConfig(self::CONFIG_PATH, $this->uuid);

        return true;
    }

    /**
     * check if certificate is already issued by ACME CA
     * @return bool
     */
    public function isIssued()
    {
        return (string)$this->config->statusCode == 200 ? true : false;
    }

    /**
     * issue or renew the certificate
     * @return bool
     */
    public function issue()
    {
        if (!($this->isEnabled())) {
            LeUtils::log('ignoring disabled certificate: ' . (string)$this->config->name);
            return false;
        }

        // Issue or renew?
        if (!empty((string)$this->config->lastUpdate) and !($this->force)) {
            $acme_action = 'renew';
            $renew = true;
        } else {
            // Default: Issue a new certificate.
            // If "force" is specified, forcefully re-issue the cert, no matter if it's required.
            // NOTE: This is useful when switching from acme staging to production servers.
            $acme_action = 'issue';
            $renew = false;
        }

        // Decide whether or not to continue.
        if (!($this->needsRenewal()) and !($this->force)) {
            // Renewal not required. Do nothing.
            LeUtils::log("issue/renewal not required for certificate: " . (string)$this->config->name);
            return false;
        }

        // Get auto renewal plugin setting.
        $configObj = Config::getInstance()->object();
        $auto_renewal = $configObj->OPNsense->AcmeClient->settings->autoRenewal;

        // Check if called by auto renewal process.
        if (($acme_action == 'renew') and ($this->cron == 1) and ($auto_renewal == 0)) {
            LeUtils::log('auto renewal is globally disabled, skipping certificate: ' . (string)$this->config->name);
            return false;
        } elseif (($acme_action == 'renew') and ($this->cron == 1) and ((string)$this->config->autoRenewal == 0)) {
            LeUtils::log('auto renewal is disabled for certificate: ' . (string)$this->config->name);
            return false;
        }
        LeUtils::log("{$acme_action} certificate: " . (string)$this->config->name);
        LeUtils::log('using CA: ' . $this->ca);

        // Ensure that account is registered.
        if (!($this->setAccount())) {
            return false;
        }

        // Setup ACME environment for this certificate.
        $certdir = (string)sprintf(self::ACME_CERT_DIR, (string)$this->config->id);
        $keydir = (string)sprintf(self::ACME_KEY_DIR, (string)$this->config->id);
        $configdir = (string)sprintf(self::ACME_CONFIG_DIR, (string)$this->config->id);
        foreach (array($certdir, $keydir, $configdir) as $dir) {
            if (!is_dir($dir)) {
                LeUtils::log_debug("creating directory: {$dir}");
                mkdir($dir, 0700, true);
            }
        }

        // Perform preparation tasks
        if (!($this->setValidation())) {
            $this->setStatus(300);
            return false; // validation method is invalid
        }

        // acme.sh would re-run a recorded deploy hook during --renew, i.e. before
        // the certificate is imported. Remove stale hooks to avoid failed renewals.
        if ($renew) {
            $this->clearRecordedDeployHooks();
        }

        // Let's start certificate validation...
        if ($this->validation->run($renew)) {
            LeUtils::log('successfully issued/renewed certificate: ' . (string)$this->config->name);
        } elseif (!empty($this->validation->skipped)) {
            // acme.sh decided renewal isn't due yet. Not an error, leave
            // the certificate's current status untouched.
            LeUtils::log('renewal skipped by acme.sh, not due yet for certificate: ' . (string)$this->config->name);
            return false;
        } else {
            LeUtils::log_error('validation for certificate failed: ' . (string)$this->config->name);
            $this->setStatus(400);
            return false;
        }

        // Import certificate.
        if (!($this->import(true))) {
            LeUtils::log_error('failed to import certificate: ' . (string)$this->config->name);
            $this->setStatus(500);
            return false;
        }

        // Update cert status.
        $this->setStatus(200);

        // Run referenced automations.
        $this->runAutomations();

        return true;
    }

    /**
     * calculate next renewal date for this certificate
     * @return bool
     */
    public function needsRenewal()
    {
        $return = false;

        // Try to get issue date from certificate
        if (is_file($this->cert_file)) {
            // Read contents from certificate file
            $cert_content = @file_get_contents($this->cert_file);
            if ($cert_content != false) {
                $cert_info = @openssl_x509_parse($cert_content);
                if (!empty($cert_info['validFrom_time_t'])) {
                    $last_update = $cert_info['validFrom_time_t'];
                } else {
                    LeUtils::log_error('unable to get expiration time from certificate for ' . (string)$this->config->name);
                    $last_update = 0; // Just assume the cert requires renewal.
                }
            } else {
                LeUtils::log_error('unable to read certificate content from file for ' . (string)$this->config->name);
                $last_update = 0; // Just assume the cert requires renewal.
            }
        } elseif (!empty((string)$this->config->lastUpdate)) {
            // Fallback to lastUpdate() state, although it may not be correct
            // if the cert was imported manually after issue/renewal.
            $last_update = (string)$this->config->lastUpdate;
        } else {
            $last_update = 0; // Just assume the cert requires renewal.
        }

        // Collect required information
        $current_time = new \DateTime();
        $last_update_time = new \DateTime();
        $last_update_time->setTimestamp($last_update);
        $renew_interval = (string)$this->config->renewInterval;
        $next_update = $last_update_time->add(new \DateInterval('P' . $renew_interval . 'D'));

        // Do the math
        if ($current_time >= $next_update) {
            LeUtils::log('certificate must be issued/renewed: ' . (string)$this->config->name);
            $return = true;
        }

        return $return;
    }

    /**
     * completely remove the certificate and all related configuration from filesystem
     * @return bool
     */
    public function remove()
    {
        // NOTE:
        // Removal is allowed even if the cert is disabled.

        // Cannot remove if certificate was not issued yet.
        if (empty((string)$this->config->lastUpdate)) {
            LeUtils::log('ignoring removal request for certificate ' . (string)$this->config->name . ' (not issued yet)');
            return false;
        }
        LeUtils::log('wiping certificate config: ' . (string)$this->config->name);

        // Preparation to run acme client
        $proc_env = $this->acme_env; // add env variables
        $proc_env['PATH'] = $this::ACME_ENV_PATH;

        // Prepare acme.sh command to remove certificate and related config
        $acmecmd = '/usr/local/sbin/acme.sh '
          . '--remove '
          . implode(' ', $this->acme_args) . ' '
          . LeUtils::execSafe('--domain %s', (string)$this->config->name);
        LeUtils::log_debug('running acme.sh command: ' . (string)$acmecmd);

        // Run acme.sh command
        $result = LeUtils::run_shell_command($acmecmd, $proc_env);

        // Check acme.sh result
        if ($result) {
            LeUtils::log_error('error removing certificate ' . (string)$this->config->name);
            return false;
        }

        // Remove all certificate files (just to be sure)
        // NOTE: This also resets the cert status.
        $this->reset();

        return true;
    }

    /**
     * reset the certificate by removing only it's private key and the signed certificate
     * @return bool
     */
    public function reset()
    {
        // NOTE: Reset is allowed even if the cert is disabled.
        LeUtils::log('removing certificate files: ' . (string)$this->config->name);
        $cert_files = [
            $this->cert_file,
            $this->cert_key_file,
            $this->cert_chain_file,
            $this->cert_fullchain_file,
        ];
        foreach ($cert_files as $_file) {
            if (file_exists($_file)) {
                unlink($_file);
            }
        }

        // Reset cert status
        $this->setStatus(100);
        return true;
    }

    /**
     * revoke the certificate
     * @return bool
     */
    public function revoke()
    {
        // NOTE: Revocation is allowed even if the cert is disabled.

        // Revocation will fail if additional domain names were added
        // to the certificate after issue/renewal.

        // Cannot revoke if certificate was not issued yet.
        if (!($this->isIssued())) {
            LeUtils::log('ignoring revocation request for certificate ' . (string)$this->config->name . ' (not issued yet)');
            return false;
        }
        LeUtils::log('revoking certificate: ' . (string)$this->config->name);

        // Collect account information
        $account_conf_dir = self::ACME_BASE_ACCOUNT_DIR . '/' . $this->account_id . '_' . $this->ca_compat;
        $account_conf_file = $account_conf_dir . '/account.conf';

        // Preparation to run acme client
        $proc_env = $this->acme_env; // add env variables
        $proc_env['PATH'] = $this::ACME_ENV_PATH;

        // Prepare acme.sh command to revoke certificate
        $acmecmd = '/usr/local/sbin/acme.sh '
          . '--revoke '
          . implode(' ', $this->acme_args) . ' '
          . LeUtils::execSafe('--domain %s', (string)$this->config->name) . ' '
          . LeUtils::execSafe('--accountconf %s', $account_conf_file);
        LeUtils::log_debug('running acme.sh command: ' . (string)$acmecmd);

        // Run acme.sh command
        $result = LeUtils::run_shell_command($acmecmd, $proc_env);

        // Check exit code
        if ($result) {
            LeUtils::log_error('failed to revoke certificate ' . (string)$this->config->name);
            $this->setStatus(400);
            return false;
        }
        LeUtils::log('successfully revoked certificate: ' . (string)$this->config->name);

        // Reset cert status
        $this->setStatus(250);
        return true;
    }

    /**
     * run all automations for this certificate
     * @return bool
     */
    public function runAutomations()
    {
        if (!($this->isEnabled())) {
            LeUtils::log('ignoring disabled certificate: ' . (string)$this->config->name);
            return false;
        }

        // Check if any automations are configured for this cert
        if (empty((string)$this->config->restartActions)) {
            return true; // no automations, no error
        }

        // Walk through all linked automations.
        LeUtils::log('running automations for certificate: ' . (string)$this->config->name);
        $automations = explode(',', (string)$this->config->restartActions);
        foreach ($automations as $auto_uuid) {
            $autoFactory = new LeAutomationFactory();
            $automation = $autoFactory->getAutomation($auto_uuid);
            // Skip invalid automations.
            if (!is_null($automation)) {
                $automation->init($this->getId(), (string)$this->config->name, (string)$this->config->account, $this->cert_ecc);
                if ($automation->prepare()) {
                    $automation->run();
                }
            } else {
                LeUtils::log_error("ignoring invalid automation: {$auto_uuid}");
            }
        }

        return true;
    }

    /**
     * configure and register the referenced account
     * @return bool
     */
    public function setAccount()
    {
        // Ensure that account is registered.
        $account = new LeAccount((string)$this->config->account);
        if (empty($account)) {
            $this->setStatus(300); // update cert status
            return false; // account invalid or it was deleted
        } elseif (!($account->isRegistered())) {
            $account->generateKey();
            if (!($account->register())) {
                $this->setStatus(400); // update cert status
                return false; // account registration failed
            }
            // Refresh config objects, account may have modified the configuration.
            Config::getInstance()->forceReload();
            $this->loadConfig(self::CONFIG_PATH, $this->uuid);
        }
        LeUtils::log('account is registered: ' . (string)$account->config->name);
        // Always check (and fix) account config
        $account->fixConfig();
        return true;
    }

    /**
     * configure the validation method
     * @return bool
     */
    public function setValidation()
    {
        if (empty((string)$this->validation)) {
            // Setup new validation object
            $valFactory = new LeValidationFactory();
            $val = $valFactory->getValidation((string)$this->config->validationMethod);
            if (!isset($val) or empty($val)) {
                LeUtils::log_error('invalid challenge type for certificate: ' . (string)$this->config->name);
                return false;
            }
            if (!$val->init((string)$this->config->id, (string)$this->config->account, $this->cert_ecc)) {
                LeUtils::log_error('failed to initialize validation for certificate: ' . (string)$this->config->name);
                return false;
            }

            // Configure validation object
            $val->setNames($this->config->name, $this->config->altNames, $this->config->aliasmode, $this->config->domainalias, $this->config->challengealias);
            $renewInterval = (string)$this->config->renewInterval;
            $val->setRenewal((int)$renewInterval);
            $val->setForce($this->force);
            $val->setOcsp((string)$this->config->ocsp == 1 ? true : false);
            if (!empty((string)$this->config->profile)) {
                $val->setProfile((string)$this->config->profile);
            }
            // strip prefix from key value
            $val->setKey(substr($this->config->keyLength, 4));
            $val->prepare();

            // Store validation object
            $this->validation = $val;
        }
        return true;
    }
}
