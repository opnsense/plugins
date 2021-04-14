#!/usr/local/bin/php
<?php

/*
    Copyright (C) 2020 Tobias Boehnert
    All rights reserved.
    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:
    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.
    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

require_once("config.inc");
require_once("certs.inc");
require_once("legacy_bindings.inc");

use OPNsense\Core\Config;

$outputFolder = "/usr/local/etc/radsecproxy.d/certs/";


echo "begin generating of RadSecProxy-TLS-certificates\n";
echo "output-directory: " . $outputFolder . "\n";

if (! function_exists('writeCertFile')) {
    function writeCertFile($pathToFile, $certificateData)
    {
        // generate cert pem file
        $pem_content = trim(str_replace("\n\n", "\n", str_replace(
            "\r",
            "",
            base64_decode((string)$certificateData)
        )));

        $pem_content .= "\n";
        file_put_contents($pathToFile, $pem_content);
        chmod($pathToFile, 0600);
        echo "generated file " . $pathToFile . "\n";
    }
}

if (! function_exists('deleteFilesInFolder')) {
    function deleteFilesInFolder($pathToFolder)
    {
        echo "deleting all files in folder " . $pathToFolder . "\n";
        $files = glob($pathToFolder . '/*');

        foreach ($files as $file) {
            //Make sure that this is a file and not a directory.
            if (is_file($file)) {
                //Use the unlink function to delete the file.
                unlink($file);
                echo "deleted file " . $file . "\n";
            }
        }
    }
}

// traverse radsecproxy's tls-configs
$configObj = Config::getInstance()->object();

deleteFilesInFolder($outputFolder);
if (isset($configObj->OPNsense->radsecproxy->tlsConfigs)) {
    foreach ($configObj->OPNsense->radsecproxy->tlsConfigs->children() as $tlsConfig) {
        echo "parsing TLS-config \"" . $tlsConfig->name . "\"\n";

        $caCertRefId = (string)$tlsConfig->caCertificateRefId;
        $proxyCertRefId = (string)$tlsConfig->proxyCertificateRefId;

        if ($caCertRefId != "") {
            echo "looking for CA-cert-file\n";
            foreach ($configObj->ca as $ca) {
                if ($caCertRefId == (string)$ca->refid) {
                    echo "creating CA-cert-files from \"" . $ca->descr . "\"\n";
                    writeCertFile($outputFolder . $tlsConfig->name . "_ca-cert.pem", $ca->crt);
                }
            }
        }

        if ($proxyCertRefId != "") {
            foreach ($configObj->cert as $cert) {
                if ($proxyCertRefId == (string)$cert->refid) {
                    echo "creating proxy-cert-files from \"" . $cert->descr . "\"\n";
                    writeCertFile($outputFolder . $tlsConfig->name . "_proxy-cert.pem", $cert->crt);
                    writeCertFile($outputFolder . $tlsConfig->name . "_proxy-key.pem", $cert->prv);
                }
            }
        }
    }
} else {
    echo "no TLS-configs found\n";
}
