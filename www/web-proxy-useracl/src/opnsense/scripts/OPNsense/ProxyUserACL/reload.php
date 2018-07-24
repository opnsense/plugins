#!/usr/bin/env php
<?php

/**
 *    Copyright (C) 2017-2018 Smart-Soft
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

require_once('script/load_phalcon.php');

use \OPNsense\ProxyUserACL\ProxyUserACL;
use \OPNsense\Core\Config;
use \OPNsense\Proxy\Proxy;

$mdlProxyUserACL = new ProxyUserACL();
$domain = strtoupper((string)Config::getInstance()->object()->system->domain);

array_map('unlink', glob("/usr/local/etc/squid/ACL_useracl_*.txt"));
foreach ($mdlProxyUserACL->getNodeByReference('general.Users.User')->getChildren() as $User) {
    foreach (explode(",", (new Proxy())->forward->authentication->method->__toString()) as $method) {
        if ($User->Server->__toString() == $method) {
            if ($User->Group->__toString() == "user") {
                $users = [];
                foreach (array_filter(explode(",", $User->Names->__toString())) as $user) {
                    $users[] = urlencode($user) . "@" . $domain;
                }
                $domain_users = implode("\n", $users);
            } else {
                $domain_users = "";
            }
            file_put_contents("/usr/local/etc/squid/ACL_useracl_" . $User->id->__toString() . ".txt",
                implode("\n", array_filter(explode(",",
                    $User->Names->__toString()))) . "\n" . $domain_users . "\n");
            break;
        }
    }
}
