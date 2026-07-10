#!/usr/local/bin/php
<?php

/*
 * Persist the safe DNSBL-off state after named's startup memory guard trips.
 * The selected lists remain configured, so an administrator can re-enable
 * DNSBL after reducing the selection or adding memory.
 */

require_once("util.inc");
require_once("config.inc");

if (!isset($config['OPNsense']['bind']['dnsbl']) ||
    !is_array($config['OPNsense']['bind']['dnsbl'])) {
    exit(1);
}

if (($config['OPNsense']['bind']['dnsbl']['enabled'] ?? '0') !== '1') {
    exit(0);
}

$config['OPNsense']['bind']['dnsbl']['enabled'] = '0';
write_config('Disabled BIND DNSBL after the DNSBL startup memory guard reached the free-memory floor.');

exit(0);
