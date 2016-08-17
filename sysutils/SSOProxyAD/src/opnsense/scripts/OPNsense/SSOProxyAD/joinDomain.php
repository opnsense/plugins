#!/usr/local/bin/php
<?php

/**
 *    Copyright (C) 2016 gitdevmod@github.com
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

$configObj = Config::getInstance()->object();
$hostname = $configObj->system->hostname;
$fqdn = $hostname . "." . $configObj->system->domain;
if (isset($configObj->OPNsense->ssoproxyad)) {
    foreach ($configObj->OPNsense->ssoproxyad->general as $ssoproxyad) {
	$enabled = $ssoproxyad->Enabled;
	$domainname = $ssoproxyad->DomainName;
	$domaindc = $ssoproxyad->DomainDC;
	$domainversion = $ssoproxyad->DomainVersion;
	$domainuser = $ssoproxyad->DomainUser;
	$domainpassword = $ssoproxyad->DomainPassword;
    }
}

$cmd_2003 = '/usr/local/sbin/msktutil -c -b CN=COMPUTERS -s HTTP -k ' . $keytab . ' --computer-name ' . strtoupper($hostname) . ' --upn HTTP/' . $fqdn. ' --server ' . $domaindc . ' --verbose 2>&1';
$cmd_2008 = '/usr/local/sbin/msktutil -c -b CN=COMPUTERS -s HTTP -k ' . $keytab . ' --computer-name ' . strtoupper($hostname) . ' --upn HTTP/' . $fqdn. ' --server ' . $domaindc . ' --enctypes 28 --verbose 2>&1';

if ($enabled == 1) {
	$keytab = '/usr/local/etc/ssoproxyad/PROXY.keytab';
	$krb5secret = '/usr/local/etc/ssoproxyad/krb5secret';
	if ( !file_exists($keytab) ) {
		file_put_contents($krb5secret, $domainpassword);
		chmod($krb5secret, 0600);
		exec('/usr/bin/kinit --password-file="' . $krb5secret . '" ' . $domainuser. "@" . strtoupper($domainname) . " 2>&1",$output_kinit,$error_kinit);
		if ($error_kinit > 0) {
			$return = array('message' => "$output_kinit");
		} 
		else {
			if ( $domainversion == '2003' ) {
				exec($cmd_2003,$output_msktutil,$error_msktutil);
			} elseif ( $domainversion == '2008' ) {
				exec($cmd_2008,$output_msktutil,$error_msktutil);
			}
			if ( (file_exists($keytab)) and ($error_msktutil <= 0) ) {
				chown($keytab,'squid');
				chgrp($keytab,'squid');
				exec("/usr/bin/kdestroy 2>&1",$output_kdestroy,$error_kdestroy);
				unlink($krb5secret);
				$return = array('message' => "keytab created");
			}
			else {
				$out = implode($output_msktutil);
				$return = array('message' => "Unable to create keytab: $out");
			}
		}
	}
	else {
		$return = array('message' => "keytab already exists");
	}
}
echo json_encode($return);
