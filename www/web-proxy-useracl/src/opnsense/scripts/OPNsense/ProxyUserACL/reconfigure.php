#!/usr/bin/env php
<?php

require_once('script/load_phalcon.php');

use \OPNsense\ProxyUserACL\ProxyUserACL;
use \OPNsense\Core\Config;

$mdlProxyUserACL = new ProxyUserACL();
$domain = strtoupper((string) Config::getInstance()->object()->system->domain);

array_map('unlink', glob("/usr/local/etc/squid/ACL_*.txt"));
foreach ($mdlProxyUserACL->getNodeByReference('general.ACLs.ACL')->getNodes() as $acl)
    file_put_contents("/usr/local/etc/squid/ACL_" . $acl["Priority"] . ".txt", $acl["Name"] . "\n" . ($acl["Group"]["user"]["selected"] == "1" ? $acl["Name"] . "@" . $domain . "\n" : ""));
