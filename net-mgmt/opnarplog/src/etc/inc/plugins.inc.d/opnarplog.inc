<?php

/*
 * Copyright (C) 2025 github.com/mr-manuel
 * All rights reserved.
 *
 * License: BSD 2-Clause
 */

function arp_ndp_logging_services()
{
    global $config;

    $services = array();

    if (isset($config['OPNsense']['_'][_general']['enabled']) && $config['OPNsense']['arp-ndp-logging']['general']['enabled'] == 1) {
        $services[] = array(
            'description' => gettext('ARP/NDP Logging Daemon'),
            'configd' => array(
                'restart' => array('arp-ndp-logging restart'),
                'start' => array('arp-ndp-logging start'),
                'stop' => array('arp-ndp-logging stop'),
            ),
            'name' => 'arp-ndp-logging',
            'pidfile' => '/var/run/arp-ndp-logging.pid',
        );
    }

    return $services;
}
