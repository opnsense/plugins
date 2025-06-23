#!/usr/local/bin/php
<?php

/*
 *    Copyright (C) 2024 Volodymyr Paprotski
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

require_once("config.inc");
require_once("certs.inc");

use OPNsense\Core\Config;

$stepcadir = "/usr/local/etc/step/ca/";
$rootcert = $stepcadir . "certs/root_ca.crt";
$roottpl = $stepcadir . "templates/root.tpl";
$intcert = $stepcadir . "certs/intermediate_ca.crt";
$inttpl = $stepcadir . "templates/intermediate.tpl";
$intkey = $stepcadir . "secrets/intermediate_ca_key";
$dirs = array(
    $stepcadir,
    $stepcadir . "config",
    $stepcadir . "certs",
    $stepcadir . "secrets",
    $stepcadir . "db"
);

/*
 * By the end of this script:
 *  - Root and Intermediate Cert files are populated
 *  - Root and Intermediate Cert are present in System/Trust
 *  - (Unless KMS) Private Key for Intermediate Cert is populated
 *  - Main Config section is updated(??)
 */

function info_log($msg) {
    syslog(LOG_INFO, "stepca-initca: ".$msg);
}

function warn_log($msg) {
    syslog(LOG_WARNING, "stepca-initca: ".$msg);
}

function err_log($msg) {
    syslog(LOG_ERR, "stepca-initca: ".$msg);
}

function certFromTrust(string $certId, string $certFile, ?string $keyFile = null) : ?string {
    $certObj = lookup_ca($certId);
    if (!$certObj) {
        $msg = "no certificate found".$certObj;
        err_log($msg);
        return $msg;
    }

    $certPEM = base64_decode($certObj['crt']);
    file_put_contents($certFile, $certPEM);
    info_log("created file ".$certFile);

    if (null == $keyFile) {
        return null;
    }

    if (empty($certObj['prv'])) {
        $msg = "no private key found";
        err_log($msg);
        return $msg;
    }

    $keyPEM = base64_decode($certObj['prv']);
    file_put_contents($keyFile, $keyPEM);
    return null;
}

function createOnKMS(string $keyTypeDash, string $kmsKey, string $kmsURI, string $certFile, int $duration, string $templateFile, ?string $kmsCAKey = null, ?string $caCert = null) : ?string {
    $kmsURI = escapeshellarg($kmsURI);
    $kmsKey = escapeshellarg($kmsKey);
    $keyType = explode("-", $keyTypeDash);
    if ($keyType[0] == "RSA") {
        $privKeyCmd = "--kty RSA --size ".escapeshellarg($keyType[1]);
    } elseif ($keyType[0] == "ECC") {
        $privKeyCmd = "--kty EC --crv ".escapeshellarg($keyType[1]);
    }
    $privKeyCmd = 'step-kms-plugin create '.$privKeyCmd.' --kms '.$kmsURI.' '.$kmsKey;

    if ($kmsCAKey != null && $caCert != null) {
        $kmsCAKey = escapeshellarg($kmsCAKey);
        $certCmd = ' --ca-kms '.$kmsURI.' -ca-key '.$kmsCAKey.' --ca '.$caCert;
    }
    $certCmd = 'step certificate create --force --not-after='.(60*$duration).'h --template '.$templateFile.' --kms '.$kmsURI.' --key '.$kmsKey.$certCmd.' "Dummy CA" '.$certFile;
    $importCmd = 'step-kms-plugin certificate --import '.$certFile.' --kms '.$kmsURI.' '.$kmsKey;
    
    info_log($privKeyCmd);
    $msgl = exec($privKeyCmd, $msg, $rcCode);
    if ($rcCode != 0) {
        warn_log(join("\n", $msg));
        return "couldn't create kms private key";
    } else {
        info_log(join("\n", $msg));
    }

    info_log($certCmd);
    unset($msg);
    $msgl = exec($certCmd, $msg, $rcCode);
    if ($rcCode != 0) {
        warn_log(join("\n", $msg));
        return "couldn't create kms certificate";
    } else {
        info_log(join("\n", $msg));
    }

    info_log($importCmd);
    unset($msg);
    $msgl = exec($importCmd, $msg, $rcCode);
    if ($rcCode != 0) {
        warn_log(join("\n", $msg));
        return "couldn't create kms certificate";
    } else {
        info_log(join("\n", $msg));
    }

    return null;
}

function loadFromKMS(string $kmsKey, string $kmsURI, string $certFile) : ?string {
    // step-kms-plugin certificate --kms 'yubikey:pin-value=123456' yubikey:slot-id=9a
    // escapeshellarg()
    $kmsURI = escapeshellarg($kmsURI);
    $kmsKey = escapeshellarg($kmsKey);
    $exportCmd = 'step-kms-plugin certificate --kms '.$kmsURI.' '.$kmsKey;
    info_log($exportCmd);
    unset($msg);
    $msgl = exec($exportCmd, $msg, $rcCode);
    if ($rcCode != 0) {
        warn_log(join("\n", $msg));
        return "couldn't export kms certificate";
    }

    $certPEM = join("\n", $msg);
    file_put_contents($certFile, $certPEM."\n");
    info_log("created file ".$certFile);

    return null;
}

function setupDirs() {
    global $dirs;
    foreach ($dirs as $d) {
        $r1 = mkdir($d, 750, true);
        $r2 = chown($d, "step");
        $r3 = chgrp($d, "step");
        // if (false == $r1 && $r2 && $r3) {
        //     warn_log("failed setupDirs for ".$d." [".$r1.$r2.$r3."]");
        // }
    }
}

function addTrust(string $certFile, string &$refid, ?string $caRef = null) {
    $crtPem = file_get_contents($certFile);
    $crtX509 = openssl_x509_parse($crtPem);
    $cert = array();
    $cert['descr'] = $crtX509['subject']['CN']." (StepCA)";
    $cert['refid'] = $refid;
    $cert['crt'] = base64_encode($crtPem);
    if ($caRef) {
        $cert['caref'] = $caRef;
    }

    $config = Config::getInstance()->object();
    foreach ($config->ca as $caCrt) {
        $caX509 = openssl_x509_parse(base64_decode($caCrt->crt));
        if ($caX509['serialNumber'] == $crtX509['serialNumber'] && 
            $caX509['extensions']['subjectKeyIdentifier'] == $crtX509['extensions']['subjectKeyIdentifier']) {
            $refid = $cert['refid'];
            return false;
        }
    }

    $newca = $config->addChild('ca');
    $newca->addAttribute('uuid', generate_uuid());
    foreach (array_keys($cert) as $cacfg) {
        $newca->addChild($cacfg, (string)$cert[$cacfg]);
    }
    return true;
}

function main() : string {
    global $rootcert, $intcert, $intkey, $roottpl, $inttpl;

    $configobj = Config::getInstance()->object();
    $config = $configobj->OPNsense->StepCA->Initialize;
    $sourceR = $config->root->Source;
    $sourceI = $config->intermediate->Source;
    
    info_log("InitCA started. Root: ".$sourceR." Intermediate: ".$sourceI);
    
    if (str_starts_with($sourceR, "yubikey") || str_starts_with($sourceI, "yubikey") ) {
        system("/usr/local/etc/rc.d/pcscd start");
    } else {
        system("/usr/local/etc/rc.d/pcscd stop");
    }
    setupDirs();

    $rootID = uniqid();
    if ($sourceR == 'yubikeyC') {
        $keyTypeDash = $config->root->CreateKeyType;
        $kmsKey = 'yubikey:slot-id='.$config->root->YubikeySlot;
        $kmsURI = 'yubikey:pin-value='.$config->yubikey->Pin;
        $duration = (int)$config->root->Lifetime;
        $status = createOnKMS($keyTypeDash, $kmsKey, $kmsURI, $rootcert, $duration, $roottpl);
        if (null != $status) {
            return $status;
        }
    } elseif ($sourceR == 'yubikeyL') {
        $kmsKey = 'yubikey:slot-id='.$config->root->YubikeySlot;
        $kmsURI = 'yubikey:pin-value='.$config->yubikey->Pin;
        $status = loadFromKMS($kmsKey, $kmsURI, $rootcert);
        if (null != $status) {
            return $status;
        }
    } elseif ($sourceR == 'trust') {
        $rootID = $config->root->TrustCertificate;
        $status = certFromTrust($rootID, $rootcert);
        if (null != $status) {
            return $status;
        }
    }

    $saveConfig = false;
    if ($sourceR != 'trust') {
        $saveConfig = addTrust($rootcert, $rootID);
    }

    if ($sourceI == 'yubikeyC') {
        if ($sourceR != 'yubikeyC' && $sourceR != 'yubikeyL') {
            // could be fixed by importing private key into yubikey; perhaps useful for HA setup
            err_log("Not Implemented: Cannot create intermediate yubikey cert without root yubikey key.");
            return 'not implemented. create yubikey with another yubikey';
        }
        $keyTypeDash = $config->intermediate->CreateKeyType;
        $kmsKey = 'yubikey:slot-id='.$config->intermediate->YubikeySlot;
        $kmsURI = 'yubikey:pin-value='.$config->yubikey->Pin;
        $duration = (int)$config->intermediate->Lifetime;
        $kmsCAKey = 'yubikey:slot-id='.$config->root->YubikeySlot;
        createOnKMS($keyTypeDash, $kmsKey, $kmsURI, $intcert, $duration, $inttpl, $kmsCAKey, $rootcert);
    } elseif ($sourceR == 'yubikeyL') {
        $kmsKey = 'yubikey:slot-id='.$config->intermediate->YubikeySlot;
        $kmsURI = 'yubikey:pin-value='.$config->yubikey->Pin;
        $status = loadFromKMS($kmsKey, $kmsURI, $intcert);
        if (null != $status) {
            return $status;
        }
    } elseif ($sourceI == 'trust') {
        $certID = $config->intermediate->TrustCertificate;
        $status = certFromTrust($certID, $intcert, $intkey);
        if (null != $status) {
            return $status;
        }
    }

    if ($sourceI != 'trust') {
        $certID = uniqid();
        $saveConfig |= addTrust($intcert, $certID, $rootID);
    }

    if ($saveConfig) {
        info_log("Updating Trust with new certificates");
        Config::getInstance()->save();
    }

    return "success";
}

$status = main();
print '{"status":"'.$status.'"}';

// /usr/local/etc/rc.d/pcscd start
// $password = base64_encode(random_bytes(64));
// step-kms-plugin create --kty EC --crv P-384 --kms 'yubikey:pin-value=123456' 'yubikey:slot-id=9a';
// step certificate create --force --template /usr/local/etc/step/ca/templates/root.tpl          --kms 'yubikey:pin-value=123456' --key 'yubikey:slot-id=9a' "Smallstep Root CA" /usr/local/etc/step/ca/certs/root_ca.crt
// step certificate create --force --template /usr/local/etc/step/ca/templates/intermediate.tpl  --kms 'yubikey:pin-value=123456' --key 'yubikey:slot-id=9c' --ca-kms 'yubikey:pin-value=123456' -ca-key 'yubikey:slot-id=9a' --ca /usr/local/etc/step/ca/certs/root_ca.crt "Smallstep Intermediate CA" /usr/local/etc/step/ca/certs/intermediate_ca.crt
