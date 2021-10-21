#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2020-2021 Frank Wall
 * Copyright (C) 2019 Juergen Kellerer
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

// Import legacy code
@include_once('config.inc');
@include_once('certs.inc');
@include_once('util.inc');

// Import classes
use OPNsense\AcmeClient\LeAccount;
use OPNsense\AcmeClient\LeCertificate;

// Summary that will be displayed in usage information.
const ABOUT = <<<TXT

This script acts as a bridge between the OPNsense WebGUI/API and the
acme.sh ACME client.

TXT;

// Supported modes and their help text
const MODES = [
    'issue' => [
        'description' => 'issue or renew certificates',
    ],
    'import' => [
        'description' => 're-import certificate into trust store',
    ],
    'revoke' => [
        'description' => 'revoke the specified certificate',
    ],
    'remove' => [
        'description' => 'remove all files and configuration for the specified certificate',
    ],
    'reset' => [
        'description' => 'reset the specified certificate by removing it\'s private key',
    ],
    'automation' => [
        'description' => 'run automations for the specified certificate',
    ],
    'register' => [
        'description' => 'register the specified account with ACME CA',
    ],
];

// Supported command line options and their usage information.
const STATIC_OPTIONS = <<<TXT
-h, --help          Print commandline help
--mode              Specify the mode of operation
--cert              The certificate UUID when working with a single certificate
--all               Work with ALL enabled certificates
--account           The account UUID when working with an ACME CA account
--force             Force certain operations (i.e. renew)
--cron              Special mode when running from cron (i.e. consider auto renew settings)
TXT;

// Examples that will be display in usage information.
const EXAMPLES = <<<TXT
- Sign or renew the specified certificate
  lecert.php --mode issue --cert 00000000-0000-0000-0000-000000000000

- Sign or renew all certificates
  lecert.php --mode issue --all

- Run automations for a certificate
  lecert.php --mode automation --cert 00000000-0000-0000-0000-000000000000

- Re-import a certificate from filesystem if it was removed from Trust Store
  lecert.php --mode import --cert 00000000-0000-0000-0000-000000000000

- Completely remove a certificate (keeping the copy in Trust Store untouched)
  lecert.php --mode remove --cert 00000000-0000-0000-0000-000000000000

- When registering a new account with ACME CA
  lecert.php --mode register --account 00000000-0000-0000-0000-000000000000
TXT;

/**
 * print help and usage information
 */
function help()
{
    echo ABOUT . PHP_EOL
        . "Usage: " . basename($GLOBALS["argv"][0]) . " --mode MODE [options]" . PHP_EOL
        . PHP_EOL . STATIC_OPTIONS . PHP_EOL;

    echo PHP_EOL . 'Available modes:' . PHP_EOL;
    foreach (MODES as $name => $options) {
        echo "\"$name\" - {$options["description"]}" . PHP_EOL;
    }

    echo PHP_EOL . "Examples:" . PHP_EOL
        . str_replace('/\r\n|\n|\r/g', PHP_EOL, EXAMPLES)
        . PHP_EOL . PHP_EOL;
}

/**
 * check if the specified mode is supported
 */
function validateMode($mode)
{
    $return = false;
    foreach (MODES as $name => $options) {
        if ($mode === $name) {
            $return = true;
            break;
        }
    }
    return $return;
}

function main()
{
    // Parse command line arguments
    $options = getopt('h', ['account:', 'all', 'cert:', 'cron', 'force', 'help', 'mode:']);
    $force = isset($options['force']) ? true : false;
    $cron = isset($options['cron']) ? true : false;

    // Verify mode and arguments
    if (
        empty($options) || isset($options['h']) || isset($options['help']) ||
        (isset($options['mode']) and !validateMode($options['mode']))
    ) {
         // Not enough or invalid arguments specified.
         help();
    } elseif (($options['mode'] === 'issue') && (isset($options['cert']) || isset($options['all']))) {
        // Work on all or only on a single certificate
        if (isset($options['all'])) {
            // Iterate over all certificates
            $config = OPNsense\Core\Config::getInstance()->object();
            $acme = $config->OPNsense->AcmeClient;

            // Iterate over all certificates
            foreach ($acme->certificates->children() as $certCfg) {
                $cert_uuid = (string)$certCfg->attributes()['uuid'];
                $cert = new LeCertificate($cert_uuid, $force, $cron);
                // NOTE: Disabled certificates are automatically ignored by LeCertificate.
                $cert->issue();
            }
        } else {
            // NOTE: Disabled certificates are automatically ignored by LeCertificate.
            $cert = new LeCertificate($options['cert'], $force);
            $cert->issue();
        }
    } elseif ($options['mode'] === 'import' && isset($options['cert'])) {
        $cert = new LeCertificate($options['cert']);
        // Set $skip_validation to allow import even when validation
        // is currently failing.
        $cert->import(true);
    } elseif ($options['mode'] === 'revoke' && isset($options['cert'])) {
        $cert = new LeCertificate($options['cert']);
        $cert->revoke();
    } elseif ($options['mode'] === 'remove' && isset($options['cert'])) {
        $cert = new LeCertificate($options['cert']);
        $cert->remove();
    } elseif ($options['mode'] === 'reset' && isset($options['cert'])) {
        $cert = new LeCertificate($options['cert']);
        $cert->reset();
    } elseif ($options['mode'] === 'automation' && isset($options['cert'])) {
        $cert = new LeCertificate($options['cert']);
        $cert->runAutomations();
    } elseif ($options['mode'] === 'register' && isset($options['account'])) {
        $account = new LeAccount($options['account']);
        $account->register();
    } else {
        // Fallback to help
        help();
    }
}

// Run!
main();
