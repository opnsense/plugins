#!/usr/local/bin/php
<?php

/**
 *    Copyright (C) 2016 Frank Wall
 *    Copyright (C) 2015 Deciso B.V.
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

// Use legacy code to export certificates to the filesystem.
require_once("config.inc");
require_once("certs.inc");
require_once("legacy_bindings.inc");
use OPNsense\Core\Config;
global $config;

// traverse HAProxy frontends
$configObj = Config::getInstance()->object();
if (isset($configObj->OPNsense->HAProxy->frontends)) {
    foreach ($configObj->OPNsense->HAProxy->frontends->children() as $frontend) {
        if (!isset($frontend->ssl_enabled)) {
            continue;
        }
        // multiple comma-separated values are possible
        $certs = explode(',', $frontend->ssl_certificates);
        foreach ($certs as $cert_refid) {
            // if the frontend has a cert attached, search for its contents
            if ($cert_refid != "") {
                foreach ($configObj->cert as $cert) {
                    if ($cert_refid == (string)$cert->refid) {
                        // generate cert pem file
                        $pem_content = str_replace("\n\n", "\n", str_replace("\r", "", base64_decode((string)$cert->crt)));
                        $pem_content .= "\n" . str_replace("\n\n", "\n", str_replace("\r", "", base64_decode((string)$cert->prv)));
                        $output_pem_filename = "/var/etc/haproxy/ssl/" . $cert_refid . ".pem" ;
                        file_put_contents($output_pem_filename, $pem_content);
                        chmod($output_pem_filename, 0600);
                        echo "certificate exported to " . $output_pem_filename . "\n";
                    }
                }
            }
        }
    }
}
