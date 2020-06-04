#!/usr/local/bin/php
<?php

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
