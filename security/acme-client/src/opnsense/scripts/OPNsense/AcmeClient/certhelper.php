#!/usr/local/bin/php
<?php

/**
 *    Based in parts on certs.inc and system_camanager.php (thus the extended copyright notice).
 *
 *    Copyright (C) 2017-2018 Frank Wall
 *    Copyright (C) 2015 Deciso B.V.
 *    Copyright (C) 2010 Jim Pingle <jimp@pfsense.org>
 *    Copyright (C) 2008 Shrew Soft Inc
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

// Hello. I am the spaghetti monster. Yummy.

// Use legacy code to manage certificates.
require_once("config.inc");
require_once("certs.inc");
require_once("legacy_bindings.inc");
require_once("interfaces.inc");
require_once("util.inc");
// Some stuff requires the almighty MVC framework.
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Base;
use OPNsense\AcmeClient\AcmeClient;

global $config;
global $postponed_updates;
$postponed_updates = array();

/* CLI arguments:
 *  -a (action)
 *  -c (certificate id, NOT the uuid)
 *  -A (all certificates)
 *  -C (cron, special rules apply when running as cronjob)
 *  -F (force, rewew/recreate)
 *  -S (staging)
 */
$options = getopt("a:c:ACFS");

// Simple validation
if (!isset($options["a"]) or (!isset($options["c"]) and !isset($options["A"]))) {
    // ALL actions require either a certificate ID or the -A switch
    echo "ERROR: not enough arguments\n";
    exit(1);
}
if (($options["a"] == 'revoke') and !isset($options["c"])) {
    echo "ERROR: option revoke requires a certificate ID\n";
    exit(1);
}

// Cron mode
if (isset($options["C"])) {
    // Automatically work on ALL certificates
    $options["A"] = "";
}

// Run the specified action
switch ($options["a"]) {
    case 'sign':
        //$result = sign_or_renew_cert($options["c"]);
        $result = cert_action_validator($options["c"]);
        echo json_encode(array('status'=>$result));
        break;
    case 'renew':
        //$result = sign_or_renew_cert($options["c"]);
        $result = cert_action_validator($options["c"]);
        echo json_encode(array('status'=>$result));
        break;
    case 'revoke':
        //$result = revoke_cert($options["c"]);
        $result = cert_action_validator($options["c"]);
        echo json_encode(array('status'=>$result));
        exit(1);
    case 'cleanup':
        // TODO: remove certs from filesystem if they cannot be found in config.xml
        echo "XXX: not yet implemented\n";
        exit(1);
    default:
        echo "ERROR: invalid argument specified\n";
        log_error("invalid argument specified");
        exit(1);
}

// Write certificate status updates to configuration
dump_postponed_updates();

// ALL certificate work starts here. First we do some common validation and
// make sure that everything is prepared for acme client to run.
// The actual issue/renew/revoke work is done by separate functions.
function cert_action_validator($opt_cert_id)
{
    global $options;

    $modelObj = new OPNsense\AcmeClient\AcmeClient;

    // Store certs here after successful issue/renewal. Required for restart actions.
    $restart_certs = array();

    // Search for cert ID in configuration
    $configObj = Config::getInstance()->object();
    if (isset($configObj->OPNsense->AcmeClient->certificates) && $configObj->OPNsense->AcmeClient->certificates->count() > 0) {
        foreach ($configObj->OPNsense->AcmeClient->certificates->children() as $certObj) {
            // Extract cert ID
            $cert_id = (string)$certObj->id;
            if (empty($cert_id)) {
                continue; // Cert is invalid, skip it.
            }

            // Either work with ALL certificates or check if cert ID matches
            if (isset($options["A"]) or ((string)$cert_id == (string)$opt_cert_id)) {
                // Ignore disabled certificates
                if ($certObj->enabled == 0) {
                    if (isset($options["A"])) {
                        continue; // skip to next item
                    }
                    return(1); // Cert is disabled, skip it.
                }

                // Extract Account from referenced obj
                $acctRef = (string)$certObj->account;
                $acctObj = null;
                $acctref_found = false;
                foreach ($modelObj->getNodeByReference('accounts.account')->__items as $node) {
                    if ((string)$node->getAttributes()["uuid"] == $acctRef) {
                        $acctref_found = true;
                        $acctObj = $node;
                        break; // Match! Go ahead.
                    }
                }

                // Make sure we found the configured account
                if ($acctref_found == true) {
                    // Ensure that this account was properly setup and registered.
                    $acct_result = run_acme_account_registration($acctObj, $certObj, $modelObj);
                    if (!$acct_result) {
                        //echo "DEBUG: account registration OK\n";
                    } else {
                        //echo "DEBUG: account registration failed\n";
                        log_error("AcmeClient: account registration failed");
                        log_cert_acme_status($certObj, $modelObj, '400');
                        if (isset($options["A"])) {
                            continue; // skip to next item
                        }
                        return(1);
                    }
                } else {
                    //echo "DEBUG: account not found\n";
                    log_error("AcmeClient: account not found");
                    log_cert_acme_status($certObj, $modelObj, '300');
                    if (isset($options["A"])) {
                        continue; // skip to next item
                    }
                    return(1);
                }

                // Extract Validation Method from referenced obj
                $valRef = (string)$certObj->validationMethod;
                $valObj = null;
                $ref_found = false;
                foreach ($modelObj->getNodeByReference('validations.validation')->__items as $node) {
                    if ((string)$node->getAttributes()["uuid"] == $valRef) {
                        $ref_found = true;
                        $valObj = $node;
                        break; // Match! Go ahead.
                    }
                }

                // Make sure we found the configured validation method
                if ($ref_found == true) {
                    // Was a revocation requested?
                    // NOTE: Revocation is not even considered when some elements have already been
                    //       deleted from the GUI. It's likely that it would fail anyway.
                    if ($options["a"] == "revoke") {
                        // Start acme client to revoke the certificate
                        $rev_result = revoke_cert($certObj, $valObj, $acctObj);
                        if (!$rev_result) {
                            log_cert_acme_status($certObj, $modelObj, '250');
                            return(0); // Success!
                        } else {
                            // Revocation failure
                            log_error("AcmeClient: revocation for certificate failed");
                            log_cert_acme_status($certObj, $modelObj, '400');
                            if (isset($options["A"])) {
                                continue; // skip to next item
                            }
                            return(1);
                        }
                    }

                    // Which validation method?
                    if ((string)$valObj->method == 'http01' or ((string)$valObj->method == 'dns01')) {
                        // Start acme client to issue or renew certificate
                        $val_result = run_acme_validation($certObj, $valObj, $acctObj);
                        if (!$val_result) {
                            log_error("AcmeClient: issued/renewed certificate: " . (string)$certObj->name);
                            // Import certificate to Cert Manager
                            if (!import_certificate($certObj, $modelObj)) {
                                //echo "DEBUG: cert import done\n";
                                // Prepare certificate for restart action
                                $restart_certs[] = $certObj;
                                log_cert_acme_status($certObj, $modelObj, '200');
                            } else {
                                log_error("AcmeClient: unable to import certificate: " . (string)$certObj->name);
                                log_cert_acme_status($certObj, $modelObj, '500');
                                if (isset($options["A"])) {
                                    continue; // skip to next item
                                }
                                return(1);
                            }
                        } elseif ($val_result == '99') {
                            // Renewal not required. Do nothing.
                        } else {
                            // validation failure
                            log_error("AcmeClient: validation for certificate failed: " . (string)$certObj->name);
                            log_cert_acme_status($certObj, $modelObj, '400');
                            if (isset($options["A"])) {
                                continue; // skip to next item
                            }
                            return(1);
                        }
                    } else {
                        log_error("AcmeClient: invalid validation method specified: " . (string)$valObj->method);
                        log_cert_acme_status($certObj, $modelObj, '300');
                        if (isset($options["A"])) {
                            continue; // skip to next item
                        }
                        return(1);
                    }
                } else {
                    log_error("AcmeClient: validation method not found for cert " . $certObj->name);
                    log_cert_acme_status($certObj, $modelObj, '300');
                    if (isset($options["A"])) {
                        continue; // skip to next item
                    }
                    return(1);
                }

                // Work on ALL certificates?
                if (!isset($options["A"])) {
                    break; // Stop after first match.
                }
            }
        }
    } else {
        log_error("AcmeClient: no LE certificates found in configuration");
        return(1);
    }

    // Run restart actions if an operation was successful.
    if (!empty($restart_certs)) {
        // Execute restart actions.
        if (!run_restart_actions($restart_certs, $modelObj)) {
            # Success.
        } else {
            log_error("AcmeClient: failed to execute some restart actions");
        }
    }

    return(0);
}

// Prepare optional parameters for acme client
function eval_optional_acme_args()
{
    global $options;
    $configObj = Config::getInstance()->object();

    $acme_args = array();

    // Force certificate renewal?
    $acme_args[] = isset($options["F"]) ? "--force" : null;

    // Use LE staging environment?
    $acme_args[] = $configObj->OPNsense->AcmeClient->settings->environment == "stg" ? "--staging" : null;
    $acme_args[] = isset($options["S"]) ? "--staging" : null; // for debug purpose

    // Set log level
    $acme_args[] = $configObj->OPNsense->AcmeClient->settings->logLevel == "normal" ? "--log-level 1" : "--log-level 2";
    $acme_args[] = $configObj->OPNsense->AcmeClient->settings->logLevel == "debug" ? "--debug" : null;

    // Remove empty and duplicate elements from array
    return(array_unique(array_filter($acme_args)));
}

// Create account keys and register accounts, export/import them from/to filesystem/config.xml
function run_acme_account_registration($acctObj, $certObj, $modelObj)
{
    global $options;

    // Prepare optional parameters for acme-client
    $acme_args = eval_optional_acme_args();

    // Collect account information
    $account_conf_dir = "/var/etc/acme-client/accounts/" . $acctObj->id;
    $account_conf_file = $account_conf_dir . "/account.conf";
    $account_key_file = $account_conf_dir . "/account.key";
    $acme_conf = array();
    $acme_conf[] = "CERT_HOME='/var/etc/acme-client/home'";
    $acme_conf[] = "LOG_FILE='/var/log/acme.sh.log'";
    $acme_conf[] = "ACCOUNT_KEY_PATH='" . $account_key_file . "'";
    if (!empty((string)$acctObj->email)) {
        $acme_conf[] = "ACCOUNT_EMAIL='" . (string)$acctObj->email . "'";
    }

    // Create account configuration file
    if (!is_dir($account_conf_dir)) {
        mkdir($account_conf_dir, 0700, true);
    }
    file_put_contents($account_conf_file, (string)implode("\n", $acme_conf) . "\n");
    chmod($account_conf_file, 0600);
    //echo "DEBUG: ${account_conf_file} | ${account_key_file}\n";

    // Check if account key already exists
    if (is_file($account_key_file)) {
        //echo "DEBUG: account key found\n";
    } else {
        // Check if we have an account key in our configuration
        if (!empty((string)$acctObj->key)) {
            // Write key to disk
            file_put_contents($account_key_file, (string)base64_decode((string)$acctObj->key));
            chmod($account_key_file, 0600);
            //echo "DEBUG: exported existing account key to filesystem\n";
        } else {
            // Do not generate new key if a revocation was requested.
            if ($options["a"] == "revoke") {
                log_error("AcmeClient: account key not found, but a revocation was requested");
                return(1);
            }

            // Let acme client generate a new account key
            $acmecmd = "/usr/local/sbin/acme.sh "
              . implode(" ", $acme_args) . " "
              . "--createAccountKey "
              . "--accountkeylength 4096 "
              . "--home /var/etc/acme-client/home "
              . "--accountconf " . $account_conf_file;
            //echo "DEBUG: executing command: " . $acmecmd . "\n";
            $result = mwexec($acmecmd);

            // Check exit code
            if (!($result)) {
                //echo "DEBUG: created a new account key\n";
            } else {
                //echo "DEBUG: AcmeClient: failed to create a new account key\n";
                log_error("AcmeClient: failed to create a new account key");
                return(1);
            }

            // Read account key
            $account_key_content = @file_get_contents($account_key_file);
            if ($account_key_content == false) {
                //echo "DEBUG: AcmeClient: unable to read account key from file\n";
                log_error("AcmeClient: unable to read account key from file");
                return(1);
            }

            // Import account key into config
            $acctObj->key = base64_encode($account_key_content);
            // serialize to config and save
            $modelObj->serializeToConfig();
            Config::getInstance()->save();
        }
    }

    // Check if account was already registered
    if (!empty((string)$acctObj->lastUpdate)) {
        //echo "DEBUG: account key already registered\n";
    } else {
        // Do not register new account if a revocation was requested.
        if ($options["a"] == "revoke") {
            log_error("AcmeClient: account not registered, but a revocation was requested");
            return(1);
        }

        // Run acme client to register the account
        $acmecmd = "/usr/local/sbin/acme.sh "
          . implode(" ", $acme_args) . " "
          . "--registeraccount "
          . "--home /var/etc/acme-client/home "
          . "--accountconf " . $account_conf_file;
        //echo "DEBUG: executing command: " . $acmecmd . "\n";
        $result = mwexec($acmecmd);

        // Check exit code
        if (!($result)) {
            //echo "DEBUG: registered a new account key\n";
        } else {
            //echo "DEBUG: AcmeClient: failed to register a new account key\n";
            log_error("AcmeClient: failed to register a new account key");
            return(1);
        }

        // Set update/create time in config
        $acctObj->lastUpdate = time();
        // serialize to config and save
        $modelObj->serializeToConfig();
        Config::getInstance()->save();
    }

    return;
}

// Run acme client with HTTP-01 or DNS-01 validation to issue/renew certificate
function run_acme_validation($certObj, $valObj, $acctObj)
{
    global $options;

    // Required to run pre-defined commands.
    $backend = new Backend();

    // Collect account information
    $account_conf_dir = "/var/etc/acme-client/accounts/" . $acctObj->id;
    $account_conf_file = $account_conf_dir . "/account.conf";

    // Generate certificate filenames
    $cert_id = (string)$certObj->id;
    $cert_filename = "/var/etc/acme-client/certs/${cert_id}/cert.pem";
    $cert_chain_filename = "/var/etc/acme-client/certs/${cert_id}/chain.pem";
    $cert_fullchain_filename = "/var/etc/acme-client/certs/${cert_id}/fullchain.pem";
    $key_filename = "/var/etc/acme-client/keys/${cert_id}/private.key";

    // Setup our own ACME environment
    $certdir = "/var/etc/acme-client/certs/${cert_id}";
    $keydir = "/var/etc/acme-client/keys/${cert_id}";
    $configdir = "/var/etc/acme-client/configs/${cert_id}";
    foreach (array($certdir, $keydir, $configdir) as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
    }

    // Preparation to run acme client
    $acme_args = eval_optional_acme_args();
    $proc_env = array(); // env variables for proc_open()
    $proc_env['PATH'] = '/sbin:/bin:/usr/sbin:/usr/bin:/usr/games:/usr/local/sbin:/usr/local/bin';
    $proc_desc = array(  // descriptor array for proc_open()
        0 => array("pipe", "r"), // stdin
        1 => array("pipe", "w"), // stdout
        2 => array("pipe", "w")  // stderr
    );
    $proc_pipes = array();

    // Do we need to issue or renew the certificate?
    if (!empty((string)$certObj->lastUpdate) and !isset($options["F"])) {
        $acme_action = "renew";
    } else {
        // Default: Issue a new certificate.
        // If "-F" is specified, forcefully re-issue the cert, no matter if it's required.
        // NOTE: This is useful if altNames were changed or when switching
        // from acme staging to acme production servers.
        $acme_action = "issue";
    }

    // Calculate next renewal date
    $last_update = !empty((string)$certObj->lastUpdate) ? (string)$certObj->lastUpdate : 0;
    $renew_cert = false;
    $current_time = new \DateTime();
    $last_update_time = new \DateTime();
    $last_update_time->setTimestamp($last_update);
    $renew_interval = (string)$certObj->renewInterval;
    $next_update = $last_update_time->add(new \DateInterval('P'.$renew_interval.'D'));

    // Check if it's time to renew the cert.
    if (isset($options["F"]) or ($current_time >= $next_update)) {
        $renew_cert = true;
    } else {
        // Renewal not yet required, report special code
        return(99);
    }

    // Try HTTP-01 or DNS-01 validation?
    $val_method = (string)$valObj->method;
    $acme_validation = "";        // val.method as argument for acme.sh
    $acme_hook_options = array(); // store addition arguments for acme.sh here
    switch ($val_method) {
        case 'http01':
            $acme_validation = "--webroot /var/etc/acme-client/challenges ";
            break;
        case 'dns01':
            $acme_validation = "--dns " . (string)$valObj->dns_service . " ";
            break;
        default:
            log_error("AcmeClient: invalid validation method specified: " . (string)$valObj->method);
            return(1);
    }

    // HTTP-01: setup OPNsense internal port forward
    if (($val_method == 'http01') and ((string)$valObj->http_service == 'opnsense')) {
        // Get configured HTTP port for local lighttpd server
        $configObj = Config::getInstance()->object();
        $local_http_port = $configObj->OPNsense->AcmeClient->settings->challengePort;
        //echo "DEBUG: local http challenge port: ${local_http_port}\n";

        // Collect all IP addresses here, automatic port forward will be applied for each IP
        $iplist = array();

        // Add IP addresses from auto-discovery feature
        if ($valObj->http_opn_autodiscovery == 1) {
            $dnslist = explode(',', $certObj->altNames);
            $dnslist[] = $certObj->name;
            foreach ($dnslist as $fqdn) {
                // NOTE: This may take some time.
                //echo "DEBUG: resolving ${fqdn}\n";
                $ip_found = gethostbyname("${fqdn}.");
                if (!empty($ip_found)) {
                    //echo "DEBUG: got ip ${ip_found}\n";
                    $iplist[] = (string)$ip_found;
                }
            }
        }

        // Add IP addresses from user input
        $additional_ip = (string)$valObj->http_opn_ipaddresses;
        if (!empty($additional_ip)) {
            foreach (explode(',', $additional_ip) as $ip) {
              //echo "DEBUG: additional IP ${ip}\n";
                $iplist[] = $ip;
            }
        }

        // Add IP address from chosen interface
        if (!empty((string)$valObj->http_opn_interface)) {
            $interface_ip = get_interface_ip((string)$valObj->http_opn_interface);
            if (!empty($interface_ip)) {
                //echo "DEBUG: interface " . (string)$valObj->http_opn_interface . ", IP ${interface_ip}\n";
                $iplist[] = $interface_ip;
            }
        }

        // Generate rules for all IP addresses
        $anchor_rules = "";
        if (!empty($iplist)) {
            $dedup_iplist = array_unique($iplist);
            // Add one rule for every IP
            foreach ($dedup_iplist as $ip) {
                if ($ip == '.') {
                    continue; // skip broken entries
                }
                $anchor_rules .= "rdr pass inet proto tcp from any to ${ip} port 80 -> 127.0.0.1 port ${local_http_port}\n";
            }
        } else {
            log_error("AcmeClient: no IP addresses found to setup port forward");
            return(1);
        }

        // Abort if no rules were generated
        if (empty($anchor_rules)) {
            log_error("AcmeClient: unable to setup a port forward (empty ruleset)");
            return(1);
        }

        // Create temporary port forward to allow acme challenges to  get through
        $anchor_setup = "rdr-anchor \"acme-client\"\n";
        file_put_contents("${configdir}/acme_anchor_setup", $anchor_setup);
        chmod("${configdir}/acme_anchor_setup", 0600);
        mwexec("/sbin/pfctl -f ${configdir}/acme_anchor_setup");
        file_put_contents("${configdir}/acme_anchor_rules", $anchor_rules);
        chmod("${configdir}/acme_anchor_rules", 0600);
        mwexec("/sbin/pfctl -a acme-client -f ${configdir}/acme_anchor_rules");
    }

    // Prepare DNS-01 hooks
    if ($val_method == 'dns01') {
        // Some common stuff
        $secret_key_filename = "${configdir}/secret.key";
        $acme_args[] = '--dnssleep ' . $valObj->dns_sleep;

        // Setup DNS hook:
        // Set required env variables, write secrets to files, etc.
        switch ((string)$valObj->dns_service) {
            case 'dns_ad':
                $proc_env['AD_API_KEY'] = (string)$valObj->dns_ad_key;
                break;
            case 'dns_ali':
                $proc_env['Ali_Key'] = (string)$valObj->dns_ali_key;
                $proc_env['Ali_Secret'] = (string)$valObj->dns_ali_secret;
                break;
            case 'dns_aws':
                $proc_env['AWS_ACCESS_KEY_ID'] = (string)$valObj->dns_aws_id;
                $proc_env['AWS_SECRET_ACCESS_KEY'] = (string)$valObj->dns_aws_secret;
                break;
            case 'dns_cf':
                $proc_env['CF_Key'] = (string)$valObj->dns_cf_key;
                $proc_env['CF_Email'] = (string)$valObj->dns_cf_email;
                break;
            case 'dns_cloudns':
                $proc_env['CLOUDNS_AUTH_ID'] = (string)$valObj->dns_cloudns_auth_id;
                $proc_env['CLOUDNS_SUB_AUTH_ID'] = (string)$valObj->dns_cloudns_sub_auth_id;
                $proc_env['CLOUDNS_AUTH_PASSWORD'] = (string)$valObj->dns_cloudns_auth_password;
                break;
            case 'dns_cx':
                $proc_env['CX_Key'] = (string)$valObj->dns_cx_key;
                $proc_env['CX_Secret'] = (string)$valObj->dns_cx_secret;
                break;
            case 'dns_cyon':
                $proc_env['CY_Username'] = (string)$valObj->dns_cyon_user;
                $proc_env['CY_Password'] = (string)$valObj->dns_cyon_user;
                break;
            case 'dns_dgon':
                $proc_env['DO_API_KEY'] = (string)$valObj->dns_dgon_key;
                break;
            case 'dns_dnsimple':
                $proc_env['DNSimple_OAUTH_TOKEN'] = (string)$valObj->dns_dnsimple_token;
                break;
            case 'dns_do':
                $proc_env['DO_PID'] = (string)$valObj->dns_do_pid;
                $proc_env['DO_PW'] = (string)$valObj->dns_do_password;
                break;
            case 'dns_dp':
                $proc_env['DP_Id'] = (string)$valObj->dns_dp_id;
                $proc_env['DP_Key'] = (string)$valObj->dns_dp_key;
                break;
            case 'dns_duckdns':
                $proc_env['DuckDNS_Token'] = (string)$valObj->dns_duckdns_token;
                break;
            case 'dns_dyn':
                $proc_env['DYN_Customer'] = (string)$valObj->dns_dyn_customer;
                $proc_env['DYN_Username'] = (string)$valObj->dns_dyn_user;
                $proc_env['DYN_Password'] = (string)$valObj->dns_dyn_password;
                break;
            case 'dns_dynu':
                $proc_env['Dynu_ClientId'] = (string)$valObj->dns_dynu_clientid;
                $proc_env['Dynu_Secret'] = (string)$valObj->dns_dynu_secret;
                break;
            case 'dns_freedns':
                $proc_env['FREEDNS_User'] = (string)$valObj->dns_freedns_user;
                $proc_env['FREEDNS_Password'] = (string)$valObj->dns_freedns_password;
                break;
            case 'dns_gandi_livedns':
                $proc_env['GANDI_LIVEDNS_KEY'] = (string)$valObj->dns_gandi_livedns_key;
                break;
            case 'dns_gd':
                $proc_env['GD_Key'] = (string)$valObj->dns_gd_key;
                $proc_env['GD_Secret'] = (string)$valObj->dns_gd_secret;
                break;
            case 'dns_he':
                $proc_env['HE_Username'] = (string)$valObj->dns_he_user;
                $proc_env['HE_Password'] = (string)$valObj->dns_he_password;
                break;
            case 'dns_infoblox':
                $proc_env['Infoblox_Creds'] = (string)$valObj->dns_infoblox_credentials;
                $proc_env['Infoblox_Server'] = (string)$valObj->dns_infoblox_server;
                break;
            case 'dns_ispconfig':
                $proc_env['ISPC_User'] = (string)$valObj->dns_ispconfig_user;
                $proc_env['ISPC_Password'] = (string)$valObj->dns_ispconfig_password;
                $proc_env['ISPC_Api'] = (string)$valObj->dns_ispconfig_api;
                $proc_env['ISPC_Api_Insecure'] = (string)$valObj->dns_ispconfig_insecure;
                break;
            case 'dns_lexicon':
                $proc_env['PROVIDER'] = (string)$valObj->dns_lexicon_provider;
                $proc_env['LEXICON_CLOUDFLARE_USERNAME'] = (string)$valObj->dns_lexicon_user;
                $proc_env['LEXICON_CLOUDFLARE_TOKEN'] = (string)$valObj->dns_lexicon_token;
                $proc_env['LEXICON_NAMESILO_TOKEN'] = (string)$valObj->dns_lexicon_token;
                if ((string)$valObj->dns_lexicon_provider == 'namesilo') {
                    // Namesilo applies changes to DNS records only every 15 minutes.
                    $acme_hook_options[] = "--dnssleep 960";
                }
                break;
            case 'dns_linode':
                $proc_env['LINODE_API_KEY'] = (string)$valObj->dns_linode_key;
                // Linode can take up to 15 to update DNS records
                $acme_hook_options[] = "--dnssleep 960";
                break;
            case 'dns_lua':
                $proc_env['LUA_Key'] = (string)$valObj->dns_lua_key;
                $proc_env['LUA_Email'] = (string)$valObj->dns_lua_email;
                break;
            case 'dns_me':
                $proc_env['ME_Key'] = (string)$valObj->dns_me_key;
                $proc_env['ME_Secret'] = (string)$valObj->dns_me_secret;
                break;
            case 'dns_namecom':
                $proc_env['Namecom_Username'] = (string)$valObj->dns_namecom_user;
                $proc_env['Namecom_Token'] = (string)$valObj->dns_namecom_token;
                break;
            case 'dns_nsone':
                $proc_env['NS1_Key'] = (string)$valObj->dns_nsone_key;
                break;
            case 'dns_nsupdate':
                // Write secret key to filesystem
                $secret_key_data = (string)$valObj->dns_nsupdate_key . "\n";
                file_put_contents($secret_key_filename, $secret_key_data);

                $proc_env['NSUPDATE_KEY'] = $secret_key_filename;
                $proc_env['NSUPDATE_SERVER'] = (string)$valObj->dns_nsupdate_server;
                break;
            case 'dns_ovh':
                $proc_env['OVH_AK'] = (string)$valObj->dns_ovh_app_key;
                $proc_env['OVH_AS'] = (string)$valObj->dns_ovh_app_secret;
                $proc_env['OVH_CK'] = (string)$valObj->dns_ovh_consumer_key;
                $proc_env['OVH_END_POINT'] = (string)$valObj->dns_ovh_endpoint;
                break;
            case 'dns_pdns':
                $proc_env['PDNS_Url'] = (string)$valObj->dns_pdns_url;
                $proc_env['PDNS_ServerId'] = (string)$valObj->dns_pdns_serverid;
                $proc_env['PDNS_Token'] = (string)$valObj->dns_pdns_token;
                break;
            case 'dns_vscale':
                $proc_env['VSCALE_API_KEY'] = (string)$valObj->dns_vscale_key;
                break;
            case 'dns_yandex':
                $proc_env['PDD_Token'] = (string)$valObj->dns_yandex_token;
                break;
            default:
                log_error("AcmeClient: invalid DNS-01 service specified: " . (string)$valObj->dns_service);
                return(1);
        }
    }

    // Prepare altNames
    $altnames = "";
    if (!empty((string)$certObj->altNames)) {
        $_altnames = explode(",", (string)$certObj->altNames);
        foreach (explode(",", (string)$certObj->altNames) as $altname) {
            $altnames .= "--domain ${altname} ";
        }
    }

    // Teach acme.sh about DNS API hook location
    $proc_env['_SCRIPT_HOME'] = '/usr/local/share/examples/acme.sh';

    // Get the chosen key length from xml and trim the parameter before passing to acme client
    $key_length = (string) $certObj->keyLength;
    $key_length = substr($key_length, 4);
    if ($key_length == 'ec256' || $key_length == 'ec384') {
        $key_length = substr_replace($key_length, '-', 2, 0);
    }

    // Run acme client
    // NOTE: We "export" certificates to our own directory, so we don't have to deal
    // with domain names in filesystem, but instead can use the ID of our certObj.
    $acmecmd = "/usr/local/sbin/acme.sh "
      . implode(" ", $acme_args) . " "
      . "--${acme_action} "
      . "--domain " . (string)$certObj->name . " "
      . $altnames
      . $acme_validation . " "
      . "--home /var/etc/acme-client/home "
      . "--keylength " . $key_length . " "
      . "--accountconf " . $account_conf_file . " "
      . "--certpath ${cert_filename} "
      . "--keypath ${key_filename} "
      . "--capath ${cert_chain_filename} "
      . "--fullchainpath ${cert_fullchain_filename} "
      . implode(" ", $acme_hook_options);
    //echo "DEBUG: executing command: " . $acmecmd . "\n";
    $proc = proc_open($acmecmd, $proc_desc, $proc_pipes, null, $proc_env);

    // Make sure the resource could be setup properly
    if (is_resource($proc)) {
        // Close all pipes
        fclose($proc_pipes[0]);
        fclose($proc_pipes[1]);
        fclose($proc_pipes[2]);
        // Get exit code
        $result = proc_close($proc);
    } else {
        log_error("AcmeClient: unable to start acme client process");
        return(1);
    }

    // HTTP-01: flush OPNsense port forward rules
    if (($val_method == 'http01') and ((string)$valObj->http_service == 'opnsense')) {
        mwexec('/sbin/pfctl -a acme-client -F all');
        # XXX: workaround to solve disconnection issues reported by some users
        $response = $backend->configdRun('filter reload');
    }

    // Check validation result
    if ($result) {
        log_error("AcmeClient: domain validation failed");
        return(1);
    }

    // Simply return acme clients exit code
    return($result);
}

// Revoke a certificate.
function revoke_cert($certObj, $valObj, $acctObj)
{
    // NOTE: Revocation will fail if additional domain names were added
    // to the certificate after issue/renewal.

    // Prepare optional parameters for acme-client
    $acme_args = eval_optional_acme_args();

    // Collect account information
    $account_conf_dir = "/var/etc/acme-client/accounts/" . $acctObj->id;
    $account_conf_file = $account_conf_dir . "/account.conf";

    // Generate certificate filenames
    $cert_id = (string)$certObj->id;

    // Check if EC certificate is used, if yes add the --ecc parameter to acme client
    $key_length = (string) $certObj->keyLength;
    $ecc_param =  " ";
    if ($key_length == 'key_ec256' || $key_length == 'key_ec384') {
        $ecc_param =  "--ecc";
    }

    // Run acme client
    // NOTE: We "export" certificates to our own directory, so we don't have to deal
    // with domain names in filesystem, but instead can use the ID of our certObj.
    $acmecmd = "/usr/local/sbin/acme.sh "
      . implode(" ", $acme_args) . " "
      . "--revoke "
      . "--domain " . (string)$certObj->name . " "
      . "--home /var/etc/acme-client/home "
      . "--accountconf " . $account_conf_file . " "
      . $ecc_param;
    //echo "DEBUG: executing command: " . $acmecmd . "\n";
    $result = mwexec($acmecmd);

    // TODO: maybe clear lastUpdate value?

    // Simply return acme clients exit code
    return($result);
}

function import_certificate($certObj, $modelObj)
{
    global $config;

    $cert_id = (string)$certObj->id;
    $cert_filename = "/var/etc/acme-client/certs/${cert_id}/cert.pem";
    $cert_chain_filename = "/var/etc/acme-client/certs/${cert_id}/chain.pem";
    $cert_fullchain_filename = "/var/etc/acme-client/certs/${cert_id}/fullchain.pem";
    $key_filename = "/var/etc/acme-client/keys/${cert_id}/private.key";

    // Check if certificate files can be found
    clearstatcache(); // don't let the cache fool us
    foreach (array($cert_filename, $key_filename, $cert_chain_filename, $cert_fullchain_filename) as $file) {
        if (is_file($file)) {
            // certificate file found
        } else {
            log_error("AcmeClient: unable to import certificate, file not found: ${file}");
            return(1);
        }
    }

    /*
     * Step 1: import CA
     */

    // Read contents from CA file
    $ca_content = @file_get_contents($cert_chain_filename);
    if ($ca_content != false) {
        $ca_subject = cert_get_subject($ca_content, false);
        $ca_serial  = cert_get_serial($ca_content, false);
        $ca_cn      = local_cert_get_cn($ca_content, false);
        $ca_issuer  = cert_get_issuer($ca_content, false);
        $ca_purpose = cert_get_purpose($ca_content, false);
    } else {
        log_error("AcmeClient: unable to read CA certificate content from file");
        return(1);
    }

    // Prepare CA for import in Cert Manager
    $ca = array();
    $ca['crt'] = base64_encode($ca_content);
    $ca['refid'] = uniqid();
    $ca_found = false;

    // Check if CA was previously imported
    $cacnt = 0;
    foreach ($config['ca'] as $cacrt) {
        $cacrt_subject = cert_get_subject($cacrt['crt'], true);
        $cacrt_issuer = cert_get_issuer($cacrt['crt'], true);
        if (($ca_subject == $cacrt_subject) and ($ca_issuer == $cacrt_issuer)) {
            // Use old refid instead of generating a new one
            $ca['refid'] = (string)$cacrt['refid'];
            $ca_found = true;
            break;
        }
        $cacnt++;
    }

    // Collect required CA information
    $ca_cn = local_cert_get_cn($ca_content, false);
    $ca['descr'] = (string)$ca_cn . ' (Let\'s Encrypt)';

    // Prepare CA for import
    local_ca_import($ca, $ca_content);

    // Update existing CA?
    if ($ca_found == true) {
        $config['ca'][$cacnt] = $ca;
    } else {
        // Create new CA item
        $config['ca'][] = $ca;
        log_error("AcmeClient: importing Let's Encrypt CA: ${ca_cn}");
    }

    /*
     * Step 2: import certificate
     */

    // Read contents from certificate file
    $cert_content = @file_get_contents($cert_filename);
    if ($cert_content != false) {
        $cert_subject = cert_get_subject($cert_content, false);
        $cert_serial  = cert_get_serial($cert_content, false);
        $cert_cn      = local_cert_get_cn($cert_content, false);
        $cert_issuer  = cert_get_issuer($cert_content, false);
        $cert_purpose = cert_get_purpose($cert_content, false);
      //echo "DEBUG: importing cert: subject: ${cert_subject}, serial: ${cert_serial}, issuer: ${cert_issuer} \n";
    } else {
        log_error("AcmeClient: unable to read certificate content from file");
        return(1);
    }

    // Prepare certificate for import in Cert Manager
    $cert = array();
    $cert_refid = uniqid();
    $cert['refid'] = $cert_refid;
    $cert['caref'] = (string)$ca['refid'];
    $import_log_message = 'Imported';
    $cert_found = false;

    // Check if cert was previously imported
    if (isset($certObj->certRefId)) {
        // Check if the imported certificate can still be found
        $configObj = Config::getInstance()->object();
        foreach ($configObj->cert as $cfgCert) {
            // Check if the IDs matches
            if ((string)$certObj->certRefId == (string)$cfgCert->refid) {
                $cert_found = true;
                break;
            }
        }
        // Existing cert?
        if ($cert_found == true) {
            // Use old refid instead of generating a new one
            $cert_refid = (string)$certObj->certRefId;
            $import_log_message = 'Updated';
            //echo "DEBUG: updating EXISTING certificate\n";
        }
    } else {
        // Not found. Just import as new cert.
        //echo "DEBUG: importing NEW certificate\n";
    }

    // Read private key
    $key_content = @file_get_contents($key_filename);
    if ($key_content == false) {
        log_error("AcmeClient: unable to read private key from file: ${key_filename}");
        return(1);
    }

    // Collect required cert information
    $cert_cn = local_cert_get_cn($cert_content, false);
    $cert['descr'] = (string)$cert_cn . ' (Let\'s Encrypt)';
    $cert['refid'] = $cert_refid;

    // Prepare certificate for import
    cert_import($cert, $cert_content, $key_content);

    // Update existing certificate?
    if ($cert_found == true) {
        // FIXME: Do legacy configs really depend on counters?
        $cnt = 0;
        foreach ($config['cert'] as $crt) {
            if ($crt['refid'] == $cert_refid) {
                //echo "DEBUG: found legacy cert object\n";
                $config['cert'][$cnt] = $cert;
                break;
            }
            $cnt++;
        }
    } else {
        // Create new certificate item
        $config['cert'][] = $cert;
    }

    /*
     * Step 3: update configuration
     */

    // Write changes to config
    // TODO: Legacy code, should be replaced with code from OPNsense framework
    write_config("${import_log_message} Let's Encrypt SSL certificate: ${cert_cn}");
    log_error("AcmeClient: ${import_log_message} Let's Encrypt SSL certificate: ${cert_cn}");

    // Update (acme) certificate object (through MVC framework)
    $uuid = $certObj->attributes()->uuid;
    $node = $modelObj->getNodeByReference('certificates.certificate.' . $uuid);
    if ($node != null) {
        // Add refid to certObj
        $node->certRefId = $cert_refid;
        // Set update/create time
        $node->lastUpdate = time();
        // if node was found, serialize to config and save
        $modelObj->serializeToConfig();
        Config::getInstance()->save();
    } else {
        log_error("AcmeClient: unable to update LE certificate object");
        return(1);
    }

    return(0);
}

function run_restart_actions($certlist, $modelObj)
{
    global $config;
    $return = 0;
    $configObj = Config::getInstance()->object();

    // Required to run pre-defined commands.
    $backend = new Backend();

    // NOTE: Do NOT run any restart action twice, collect duplicates first.
    $restart_actions = array();

    // Check if there's something to do.
    if (!empty($certlist) and is_array($certlist)) {
        // Extract cert object
        foreach ($certlist as $certObj) {
            // Make sure the object is functional.
            if (empty($certObj->id)) {
                log_error("AcmeClient: failed to query certificate for restart action");
                continue;
            }
            // Extract restart actions
            if (empty((string)$certObj->restartActions)) {
                // No restart actions configured.
                continue;
            }
            $_actions = explode(',', $certObj->restartActions);
            // Walk through all linked restart actions.
            foreach ($_actions as $_action) {
                // Extract restart action
                $action = $modelObj->getByActionID($_action);
                // Make sure the object is functional.
                if ($action === null) {
                    log_error("AcmeClient: failed to retrieve restart action from certificate");
                } else {
                    // Ignore disabled restart actions (even if they are still
                    // linked to a certificated).
                    if ((string)$action->enabled === "0") {
                        continue;
                    }
                    // Store by UUID, automatically eliminates duplicates.
                    $restart_actions[$_action] = $action;
                }
            }
        }
    }

    // Run the collected restart actions.
    if (!empty($restart_actions) and is_array($restart_actions)) {
        // Extract cert object
        foreach ($restart_actions as $action) {
            // Run pre-defined or custom command?
            log_error("AcmeClient: running restart action: " . $action->name);
            switch ((string)$action->type) {
                case 'restart_gui':
                    $response = $backend->configdRun('webgui restart 2', true);
                    break;
                case 'restart_haproxy':
                    $response = $backend->configdRun("haproxy restart");
                    break;
                case 'configd':
                    // Make sure a configd command was specified.
                    if (empty((string)$action->configd)) {
                        log_error("AcmeClient: no configd command specified for restart action: " . $action->name);
                        $result = '1';
                        continue; // Continue with next action.
                    }
                    $response = $backend->configdRun((string)$action->configd);
                    break;
                default:
                    log_error("AcmeClient: an invalid restart action was specified: " . (string)$action->type);
                    $return = 1;
                    continue; // Continue with next action.
            }
        }
    }

    return($return);
}

/* Update certificate object to log the status of the current acme run.
 * Supported status codes are:
 *   100     pending
 *   200     issue/renew OK
 *   250     certificate revoked
 *   300     configuration error (validation method, account, ...)
 *   400     issue/renew failed
 *   500     internal error (code issues, bad luck, unexpected errors, ...)
 * Feel free to add more status codes to make it more useful.
*/
function log_cert_acme_status($certObj, $modelObj, $statusCode)
{
    global $postponed_updates;

    $uuid = $certObj->attributes()->uuid;
    $node = $modelObj->getNodeByReference('certificates.certificate.' . $uuid);
    if ($node != null) {
        $postponed_updates[] = array(
          'uuid' => (string)$uuid,
          'statusCode' => $statusCode,
          'statusLastUpdate' => time());
    } else {
        log_error("AcmeClient: unable to update acme status for certificate " . (string)$certObj->name);
        return(1);
    }
}

/* Write postponed certificate status updates to the configuration.
 * This workaround seems to fix the "Node no longer exists" error
 * that haunted us for quite some time.
*/
function dump_postponed_updates()
{
    global $postponed_updates;
    $modelObj = new OPNsense\AcmeClient\AcmeClient;

    foreach ($postponed_updates as $pupdate) {
        $node = $modelObj->getNodeByReference('certificates.certificate.' . $pupdate['uuid']);
        if ($node != null) {
            $node->statusCode = $pupdate['statusCode'];
            $node->statusLastUpdate = $pupdate['statusLastUpdate'];
            // serialize to config and save
            $modelObj->serializeToConfig();
            Config::getInstance()->save();
        }
    }
}

// taken from certs.inc
function local_cert_get_subject_array($str_crt, $decode = true)
{
    if ($decode) {
        $str_crt = base64_decode($str_crt);
    }
    $inf_crt = openssl_x509_parse($str_crt);
    $components = $inf_crt['subject'];

    if (!is_array($components)) {
        return;
    }

    $subject_array = array();

    foreach ($components as $a => $v) {
        $subject_array[] = array('a' => $a, 'v' => $v);
    }

    return $subject_array;
}

// taken from certs.inc
function local_cert_get_cn($crt, $decode = true)
{
    $sub = local_cert_get_subject_array($crt, $decode);
    if (is_array($sub)) {
        foreach ($sub as $s) {
            if (strtoupper($s['a']) == "CN") {
                return $s['v'];
            }
        }
    }
    return "";
}

// taken from system_camanager.php
function local_ca_import(& $ca, $str, $key = "", $serial = 0)
{
    global $config;

    $ca['crt'] = base64_encode($str);
    if (!empty($key)) {
        $ca['prv'] = base64_encode($key);
    }
    if (!empty($serial)) {
        $ca['serial'] = $serial;
    }
    $subject = cert_get_subject($str, false);
    $issuer = cert_get_issuer($str, false);

    // Find my issuer unless self-signed
    if ($issuer <> $subject) {
        $issuer_crt =& lookup_ca_by_subject($issuer);
        if ($issuer_crt) {
            $ca['caref'] = $issuer_crt['refid'];
        }
    }

    /* Correct if child certificate was loaded first */
    if (is_array($config['ca'])) {
        foreach ($config['ca'] as & $oca) {
            $issuer = cert_get_issuer($oca['crt']);
            if ($ca['refid']<>$oca['refid'] && $issuer==$subject) {
                $oca['caref'] = $ca['refid'];
            }
        }
    }
    if (is_array($config['cert'])) {
        foreach ($config['cert'] as & $cert) {
            $issuer = cert_get_issuer($cert['crt']);
            if ($issuer==$subject) {
                $cert['caref'] = $ca['refid'];
            }
        }
    }
    return true;
}

function base64url_encode($str)
{
    return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
}
function base64url_decode($str)
{
    return base64_decode(str_pad(strtr($str, '-_', '+/'), strlen($str) % 4, '=', STR_PAD_RIGHT));
}

exit;
