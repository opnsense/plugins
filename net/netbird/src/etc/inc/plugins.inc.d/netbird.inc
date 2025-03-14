<?php

function netbird_enabled()
{
    $mdlNetbird = new \OPNsense\netbird\Netbird();
    return $mdlNetbird->general->Enabled->__toString() == 1;
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

