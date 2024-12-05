<?php

/*
 * Copyright (C) 2024 github.com/mr-manuel
 * All rights reserved.
 *
 */

function opnarplog_services()
{
    global $config;

    $services = array();

    if (isset($config['OPNsense']['opnarplog']['general']['enabled']) && $config['OPNsense']['opnarplog']['general']['enabled'] == 1) {
        $services[] = array(
            'description' => gettext('OPNarplog Daemon'),
            'configd' => array(
                'restart' => array('opnarplog restart'),
                'start' => array('opnarplog start'),
                'stop' => array('opnarplog stop'),
            ),
            'name' => 'opnarplog',
            'pidfile' => '/var/run/opnarplog.pid',
        );
    }

    return $services;
}
