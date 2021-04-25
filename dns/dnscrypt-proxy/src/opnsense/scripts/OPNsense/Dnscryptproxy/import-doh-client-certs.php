#!/usr/local/bin/php
<?php

/*
 *    Copyright (C) 2015 Deciso B.V.
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

// This is probably not the preferred way to do this.
// It's just something I figured out.

include('/usr/local/opnsense/mvc/script/load_phalcon.php');

use OPNsense\Core\Config;
use OPNsense\Core\Singleton;

//$cache_files = array();
//$cmd = '/usr/local/opnsense/scripts/OPNsense/Dnscryptproxy/get-relays.py';

$plugin_name = 'dnscrypt-proxy';
$plugin_dir = '/usr/local/etc/dnscrypt-proxy';

$client_cert_dir = 'doh_client_x509_auth';
$client_cert_suffix = '-client_cert.pem';
$client_cert_key_suffix = '-client_cert_key.pem';
$root_ca_cert_suffix = '-root_ca_cert.pem';

// Pre-set this to an error message in the case of unexpected interruption.
$result = 'Error';
// This bit came from the captive portal plugin, adapted for use here.

// Get the config.
$configObj = Config::getInstance()->object();

// Check that the certificate, and key are set in the config.
if (isset($configObj->OPNsense->$plugin_name->doh_client_x509_auth->creds)) {
    if ( ! is_dir($plugin_dir.'/'.$client_cert_dir)) {
        //Directory does not exist, so lets create it.
        mkdir($plugin_dir.'/'.$client_cert_dir, 0755);
    }

    foreach ($configObj->OPNsense->$plugin_name->doh_client_x509_auth->creds as $cred) {
        // Iterate through each creds entry, and create the files for each cert/key..

        // Get the UUID for use in file names to prevent collisions
        $uuid = $cred->attributes()['uuid']->__toString();

        // Open and write out our files if we have something to write.
        if (isset($cred->client_cert)) {
            $client_cert_handle = fopen($plugin_dir.'/'.$client_cert_dir.'/'.$uuid.$client_cert_suffix, 'w');
            fwrite($client_cert_handle, $cred->client_cert->__toString());
            fclose($client_cert_handle);
        }

        if (isset($cred->client_cert_key)) {
            $client_cert_key_handle = fopen($plugin_dir.'/'.$client_cert_dir.'/'.$uuid.$client_cert_key_suffix, 'w');
            fwrite($client_cert_key_handle, $cred->client_cert_key->__toString());
            fclose($client_cert_key_handle);
        }

        if (isset($cred->root_ca)) {
            $root_ca_cert_handle = fopen($plugin_dir.'/'.$client_cert_dir.'/'.$uuid.$root_ca_cert_suffix, 'w');
            fwrite($root_ca_cert_handle, $cred->root_ca->__toString());
            fclose($root_ca_cert_handle);
        }
    }
    $result = 'OK';
} else {
    // Nothing to do so everything is OK anyway.
    $result = 'OK';
}

echo $result;
