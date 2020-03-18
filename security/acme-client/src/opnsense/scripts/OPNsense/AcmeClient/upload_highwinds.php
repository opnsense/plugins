#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2019 Frank Wall
 * Copyright (C) 2015 Deciso B.V.
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

require_once("config.inc");
require_once("certs.inc");
require_once("legacy_bindings.inc");
require_once("util.inc");

use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Base;
use OPNsense\AcmeClient\AcmeClient;

$HIGHWINDS_API_URL = 'https://striketracker.highwinds.com/api/v1/accounts';

function find_certificate($acme_cert_id)
{
    $modelObj = new OPNsense\AcmeClient\AcmeClient();
    $configObj = Config::getInstance()->object();
    if (isset($configObj->OPNsense->AcmeClient->certificates) && $configObj->OPNsense->AcmeClient->certificates->count() > 0) {
        foreach ($configObj->OPNsense->AcmeClient->certificates->children() as $certObj) {
            $cert_id = (string)$certObj->id;
            $cert_name = (string)$certObj->name;
            if ($cert_id == $acme_cert_id) {
                if ($certObj->enabled == 0) {
                    log_error("AcmeClient: certificate ${cert_name} is disabled, ignoring upload request");
                    return 'None';
                }
                if (isset($certObj->certRefId)) {
                    $data = array();
                    $data['name'] = $cert_name;
                    $data['refid'] = (string)$certObj->certRefId;
                    return $data;
                } else {
                    log_error("AcmeClient: certificate ${cert_name} could not be found in trust storage, ignoring upload request");
                    break;
                }
            }
        }
        return 'None';
    }
}

function export_certificate($cert_refid)
{
    $configObj = Config::getInstance()->object();
    foreach ($configObj->cert as $cert) {
        if ($cert_refid == (string)$cert->refid) {
            $cert_content = str_replace("\n\n", "\n", str_replace("\r", "", base64_decode((string)$cert->crt)));
            $key_content = str_replace("\n\n", "\n", str_replace("\r", "", base64_decode((string)$cert->prv)));
            // check if a CA is linked
            if (!empty((string)$cert->caref)) {
                $cert = (array)$cert;
                $ca = ca_chain($cert);
                $ca_content = $ca;
            }
            $result = array();
            $result['cert'] = $cert_content;
            $result['key'] = $key_content;
            $result['ca'] = $ca_content;
            return $result;
        }
    }
    log_error("AcmeClient: cert with refid ${cert_refid} not found in trust storage");
    return 'None';
}

function upload_certificate($cert_name, $cert_refid, $acme_cert_id, $acme_automation_id)
{
    $modelObj = new OPNsense\AcmeClient\AcmeClient();
    $configObj = Config::getInstance()->object();
    if (isset($configObj->OPNsense->AcmeClient->actions) && $configObj->OPNsense->AcmeClient->actions->count() > 0) {
        foreach ($configObj->OPNsense->AcmeClient->actions->children() as $automObj) {
            $autom_id = (string)$automObj->id;
            if ($autom_id == $acme_automation_id) {
                if ($automObj->enabled == 0) {
                    log_error("AcmeClient: ignoring disabled upload job for cert ${cert_name}");
                    return 'None';
                }
                if (isset($automObj->highwinds_account_hash) && isset($automObj->highwinds_access_token)) {
                    $hw_account_hash = (string)$automObj->highwinds_account_hash;
                    $hw_access_token = (string)$automObj->highwinds_access_token;
                    $cert_data = export_certificate($cert_refid);
                    if ($cert_data !== 'None') {
                        $hw_result = hw_upload_certificate($hw_account_hash, $hw_access_token, $cert_name, $cert_data);
                        if ($hw_result !== 'None') {
                            return true;
                        }
                    }
                } else {
                    log_error("AcmeClient: upload job for cert ${cert_name} is incomplete, missing Highwinds configuration");
                    return 'None';
                }
            }
        }
        return 'None';
    }
}

function hw_list_certificates($account_hash, $access_token)
{
    global $HIGHWINDS_API_URL;
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "${HIGHWINDS_API_URL}/${account_hash}/certificates",
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 1,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer ${access_token}",
            "Content-Type: application/json",
            "User-Agent: OPNsense Firewall",
            "X-Application-Id: OPNsense Firewall"
        )
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    $http_code = $info['http_code'];
    if ($http_code != 200 || $err) {
        log_error("AcmeClient: failed to access Highwinds API, HTTP Code: ${http_code}, error ${err}");
        return 'None';
    }
    return json_decode($response);
}

function hw_get_certificate($account_hash, $access_token, $cert_name)
{
    $certificates = hw_list_certificates($account_hash, $access_token);
    if ($certificates !== 'None') {
        foreach ($certificates->list as $cert) {
            if ($cert->commonName == $cert_name) {
                return $cert;
            }
        }
    }
    return 'None';
}

function hw_upload_certificate($account_hash, $access_token, $cert_name, $cert_data)
{
    global $HIGHWINDS_API_URL;
    // Check current status of certificate at Highwinds
    $hw_cert = hw_get_certificate($account_hash, $access_token, $cert_name);
    $hw_url = 'certificates';
    $hw_method = 'POST';
    if ($hw_cert == 'None') {
        log_error("AcmeClient: cert for ${cert_name} not found in Highwinds API, starting upload...");
    } else {
        log_error("AcmeClient: cert for ${cert_name} found in Highwinds API");
        $hw_method = 'PUT';

        // Extract certificate details
        $cert = openssl_x509_parse($cert_data['cert']);
        $cert_sn = (string)$cert['serialNumber'];
        $hw_cert_sn = (string)$hw_cert->certificateInformation->serialNumber;
        $hw_cert_id = $hw_cert->id;

        // Compare local and remote certificates
        if ($cert_sn == $hw_cert_sn) {
            log_error("AcmeClient: cert ${cert_name} has same serial in Highwinds API, not updating (${cert_sn})");
            return 'None';
        }
        log_error("AcmeClient: cert serial is different in Highwinds API, updating...");
        $hw_url = "${hw_url}/${hw_cert_id}";
    }

    // adjust data format for Highwinds API
    $cert_post = json_encode(array('certificate' => $cert_data['cert'], 'key' => $cert_data['key'], 'caBundle' => $cert_data['ca']));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "${HIGHWINDS_API_URL}/${account_hash}/${hw_url}",
        CURLOPT_CUSTOMREQUEST => $hw_method,
        CURLOPT_POSTFIELDS => (string)$cert_post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 1,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SAFE_UPLOAD => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer ${access_token}",
            "Content-Type: application/json",
            "User-Agent: OPNsense Firewall",
            "X-Application-Id: OPNsense Firewall",
            "Expect:"
        )
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    $http_code = $info['http_code'];
    if ($http_code != 200 || $err) {
        log_error("AcmeClient: Failed to upload cert ${cert_name} to Highwinds API, HTTP Code: ${http_code}, error ${err}");
        return 'None';
    }
    return json_decode($response);
}

// Evaluate CLI arguments
$options = getopt("a:c:");
if (!isset($options["a"]) or !isset($options["c"])) {
    print "ERROR: not enough arguments\n";
    exit(1);
}
$acme_cert_id = $options["c"];
$acme_automation_id = $options["a"];

// Search certificate in configuration
$cert_data = find_certificate($acme_cert_id);
if ($cert_data == 'None') {
    log_error("AcmeClient: ignoring cert ID ${acme_cert_id}");
    exit(1);
} else {
    // Upload certificate (if required)
    $upload_result = upload_certificate($cert_data['name'], $cert_data['refid'], $acme_cert_id, $acme_automation_id);
    if ($upload_result === 'None') {
        log_error("AcmeClient: cert ID ${acme_cert_id} was neither uploaded nor updated");
    } else {
        log_error("AcmeClient: cert ID ${acme_cert_id} was uploaded or updated");
    }
}
exit(0);
