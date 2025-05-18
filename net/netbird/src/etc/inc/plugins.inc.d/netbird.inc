<?php

function netbird_enabled()
{
    return !(new \OPNsense\netbird\Netbird())->general->Enabled->isEmpty();
}

function netbird_services()
{
    $services = array();

    if (!netbird_enabled()) {
        return $services;
    }

    $services[] = array(
        'description' => gettext('Netbird'),
        'configd' => array(
            'restart' => array('netbird restart'),
            'start' => array('netbird start'),
            'stop' => array('netbird stop'),
        ),
        'name' => 'netbird',
        'pidfile' => '/var/run/netbird.pid'
    );

    return $services;
}

