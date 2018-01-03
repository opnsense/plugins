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

// configure ssl elements
$configNodes = [
    'frontends' => ['ssl_certificates'],
    'servers'   => ['sslCA', 'sslCRL', 'sslClientCertificate'],
];
$certTypes = ['cert', 'ca', 'crl'];

// traverse HAProxy configuration
$configObj = Config::getInstance()->object();
foreach ($configNodes as $key => $value) {
    // lookup all config nodes
    if (isset($configObj->OPNsense->HAProxy->$key)) {
        foreach ($configObj->OPNsense->HAProxy->$key->children() as $child) {
            // generate a crt-list for every child node
            $crtlist = array();
            $crtlist_filename = "/var/etc/haproxy/ssl/" . (string)$child->id . ".crtlist";
            // search in all matching child elements for ssl data
            foreach ($configNodes[$key] as $sslchild) {
                if (isset($child->$sslchild)) {
                    // multiple comma-separated values are possible
                    $certs = explode(',', $child->$sslchild);
                    foreach ($certs as $cert_refid) {
                        // if the element has a cert attached, search for its contents
                        if ($cert_refid != "") {
                            // check all known cert types
                            foreach ($certTypes as $type) {
                                // search for cert (type) in config
                                foreach ($configObj->$type as $cert) {
                                    if ($cert_refid == (string)$cert->refid) {
                                        $pem_content = '';
                                        // CRLs require special export
                                        if ($type == 'crl') {
                                            $crl =& lookup_crl($cert_refid);
                                            crl_update($crl);
                                            $pem_content = base64_decode($crl['text']);
                                        } else {
                                            $pem_content = str_replace("\n\n", "\n", str_replace("\r", "", base64_decode((string)$cert->crt)));
                                            $pem_content .= "\n" . str_replace("\n\n", "\n", str_replace("\r", "", base64_decode((string)$cert->prv)));
                                            // check if a CA is linked
                                            if (!empty((string)$cert->caref)) {
                                                $cert = (array)$cert;
                                                $ca = ca_chain($cert);
                                                $pem_content .= "\n" . $ca;
                                            }
                                        }
                                        // generate pem file
                                        $output_pem_filename = "/var/etc/haproxy/ssl/" . $cert_refid . ".pem";
                                        file_put_contents($output_pem_filename, $pem_content);
                                        chmod($output_pem_filename, 0600);
                                        echo "exported $type to " . $output_pem_filename . "\n";
                                        // add pem file to crt-list
                                        $crtlist[] = $output_pem_filename;
                                    }
                                }
                            }
                        }
                    }
                    // generate crt-list file
                    // (this makes only sense for frontends)
                    if ($key == 'frontends') {
                        // ignore if crt-list is empty
                        if (empty($crtlist)) {
                            continue;
                        }
                        // check if a default certificate is configured
                        if (isset($child->ssl_default_certificate) and (string)$child->ssl_default_certificate != "") {
                            $default_cert = (string)$child->ssl_default_certificate;
                            $default_cert_filename = "/var/etc/haproxy/ssl/" . $default_cert . ".pem";
                            // ensure default certificate is the first entry on the list
                            unset($crtlist[$default_cert]);
                            array_unshift($crtlist, $default_cert_filename);
                        }
                        $crtlist_content = implode("\n", $crtlist) . "\n";
                        file_put_contents($crtlist_filename, $crtlist_content);
                        chmod($crtlist_filename, 0600);
                        echo "exported crt-list to " . $crtlist_filename . "\n";
                    }
                }
            }
        }
    }
}
