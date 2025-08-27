<?php

/*
 * Copyright (C) 2025 github.com/mr-manuel
 * All rights reserved.
 *
 * License: BSD 2-Clause
 */

function arpndplogging_services()
{
    global $config;

    $services = array();

    if (isset($config['OPNsense']['ArpNdpLogging']['general']['enabled']) && $config['OPNsense']['ArpNdpLogging']['general']['enabled'] == 1) {
        $services[] = array(
            'description' => gettext('ArpNdpLogging Daemon'),
            'configd' => array(
                'restart' => array('arpndplogging restart'),
                'start' => array('arpndplogging start'),
                'stop' => array('arpndplogging stop'),
            ),
            'name' => 'arpndplogging',
            'pidfile' => '/var/run/arpndplogging.pid',
        );
    }

    return $services;
}
