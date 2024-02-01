#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2016-2024 Frank Wall
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

// Use legacy code to export certificates to the filesystem.
require_once("config.inc");
require_once("certs.inc");
require_once("legacy_bindings.inc");

use OPNsense\Core\Config;

$export_path = '/tmp/haproxy/ssl/';

function hasOcspInfo($cert_content)
{
    $cert_info = @openssl_x509_parse($cert_content);
    if (!empty($cert_info['name'])) {
        if (!empty($cert_info['extensions']) and !empty($cert_info['extensions']['authorityInfoAccess'])) {
            return 1;
        }
    }
    return 0;
}

// configure ssl elements
$configNodes = [
    'frontends' => ['ssl_certificates', 'ssl_clientAuthCAs', 'ssl_clientAuthCRLs', 'ssl_default_certificate'],
    'servers'   => ['sslCA', 'sslCRL', 'sslClientCertificate'],
];
$certTypes = ['cert', 'ca', 'crl'];

// traverse HAProxy configuration
$configObj = Config::getInstance()->object();
foreach ($configNodes as $key => $value) {
    // lookup all config nodes
    if (isset($configObj->OPNsense->HAProxy->$key)) {
        foreach ($configObj->OPNsense->HAProxy->$key->children() as $child) {
            // search in all matching child elements for ssl data
            foreach ($configNodes[$key] as $sslchild) {
                if (isset($child->$sslchild)) {
                    // generate a list for every known cert type
                    foreach ($certTypes as $type) {
                        // every child node needs its own set of lists
                        $crtlist = array();
                        $crtlist_filename = $export_path . (string)$child->id . "." . $type . "list";

                        // multiple comma-separated values are possible
                        $certs = explode(',', $child->$sslchild);
                        foreach ($certs as $cert_refid) {
                            // if the element has a cert attached, search for its contents
                            if ($cert_refid != "") {
                                // search for cert (type) in config
                                foreach ($configObj->$type as $cert) {
                                    if ($cert_refid == (string)$cert->refid) {
                                        $pem_content = '';
                                        $ocsp_conf = '';
                                        // CRLs require special export
                                        if ($type == 'crl') {
                                            $crl =& lookup_crl($cert_refid);
                                            $pem_content = base64_decode($crl['text']);
                                        } else {
                                            $pem_content = str_replace("\n\n", "\n", str_replace("\r", "", base64_decode((string)$cert->crt)));
                                            $pem_content .= "\n" . str_replace("\n\n", "\n", str_replace("\r", "", base64_decode((string)$cert->prv)));
                                            // Get OCSP status
                                            $ocsp_conf = hasOcspInfo($pem_content) ? ' [ocsp-update on]' : '';
                                            // check if a CA is linked
                                            if (!empty((string)$cert->caref)) {
                                                $cert = (array)$cert;
                                                $ca = ca_chain($cert);
                                                // append the CA to the certificate data
                                                $pem_content .= "\n" . $ca;
                                                // additionally export CA to it's own file,
                                                // not required for HAProxy, but makes OCSP handling easier
                                                $output_ca_filename = $export_path . $cert_refid . ".issuer";
                                                file_put_contents($output_ca_filename, $ca);
                                                chmod($output_ca_filename, 0600);
                                            }
                                        }
                                        // generate pem file for individual certs
                                        // (not supported for CRLs)
                                        if ($type == 'cert') {
                                            $output_pem_filename = $export_path . $cert_refid . ".pem";
                                            file_put_contents($output_pem_filename, $pem_content);
                                            chmod($output_pem_filename, 0600);
                                            echo "exported $type to " . $output_pem_filename . "\n";
                                            // Check if automatic OCSP updates are enabled.
                                            if (isset($configObj->OPNsense->HAProxy->general->tuning->ocspUpdateEnabled) and ($configObj->OPNsense->HAProxy->general->tuning->ocspUpdateEnabled == '1')) {
                                                $crtlist[] = $output_pem_filename . $ocsp_conf;
                                            } else {
                                                $crtlist[] = $output_pem_filename;
                                            }
                                        } else {
                                            // In contrast to certificates, CA/CRL content needs to be put in a single file.
                                            // A list of individual files is not supported by HAproxy.
                                            $crtlist[] = $pem_content;
                                        }
                                    }
                                }
                            }
                        }
                        // generate list file
                        // (only supported for frontends and servers)
                        if (($key == 'frontends') or ($key == 'servers')) {
                            // ignore if list is empty
                            if (empty($crtlist)) {
                                continue;
                            }
                            // skip for ssl_default_certificate, it's only used to ensure
                            // that the default certificate is exported to a file
                            if ($sslchild == 'ssl_default_certificate') {
                                continue;
                            }
                            // check if a default certificate is configured
                            if (($type == 'cert') and isset($child->ssl_default_certificate) and (string)$child->ssl_default_certificate != "") {
                                $default_cert = (string)$child->ssl_default_certificate;
                                // Get OCSP status
                                $ocsp_conf = '';
                                foreach ($configObj->cert as $cert) {
                                    if ($default_cert == (string)$cert->refid) {
                                        $pem_content = str_replace("\n\n", "\n", str_replace("\r", "", base64_decode((string)$cert->crt)));
                                        $ocsp_conf = hasOcspInfo($pem_content) ? ' [ocsp-update on]' : '';
                                    }
                                }
                                // Check if automatic OCSP updates are enabled.
                                if (isset($configObj->OPNsense->HAProxy->general->tuning->ocspUpdateEnabled) and ($configObj->OPNsense->HAProxy->general->tuning->ocspUpdateEnabled == '1')) {
                                    $default_cert_filename = $export_path . $default_cert . ".pem" . $ocsp_conf;
                                } else {
                                    $default_cert_filename = $export_path . $default_cert . ".pem";
                                }
                                // ensure that the default certificate is the first entry on the list
                                $crtlist = array_diff($crtlist, [$default_cert_filename]);
                                array_unshift($crtlist, $default_cert_filename);
                            }
                            $crtlist_content = implode("\n", $crtlist) . "\n";
                            file_put_contents($crtlist_filename, $crtlist_content);
                            chmod($crtlist_filename, 0600);
                            echo "exported $type list to " . $crtlist_filename . "\n";
                        }
                    }
                }
            }
        }
    }
}
