<?php

/*
 * Copyright (c) 2025 Eric Kapitanski <e@alumni.usc.edu>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE.
 */

function lightscope_enabled()
{
    $model = new \OPNsense\Lightscope\Lightscope();
    return (string)$model->general->enabled == '1';
}

function lightscope_services()
{
    $services = array();

    if (lightscope_enabled()) {
        $services[] = array(
            'description' => gettext('LightScope Cybersecurity Research Honeypot and Telescope'),
            'configd' => array(
                'restart' => array('lightscope restart'),
                'start' => array('lightscope start'),
                'stop' => array('lightscope stop'),
            ),
            'name' => 'lightscope',
            'pidfile' => '/var/run/lightscope.pid'
        );
    }

    return $services;
}

function lightscope_syslog()
{
    $logfacilities = array();

    $logfacilities['lightscope'] = array(
        'facility' => array('lightscope'),
    );

    return $logfacilities;
}

function lightscope_firewall(\OPNsense\Firewall\Plugin $fw)
{
    // Always add block+log rule when plugin is installed - no config dependency
    // This ensures pflog captures blocked TCP traffic for LightScope visibility
    // Uses very high priority to run last, catching traffic that falls through
    $fw->registerFilterRule(
        999999,  // highest priority - run as late as possible
        array(
            'type' => 'block',
            'from' => 'any',
            'to' => '(self)',
            'direction' => 'in',
            'protocol' => 'tcp',
            'descr' => 'LightScope: log blocked TCP',
            'log' => true,
            '#ref' => 'lightscope_blocklog'
        ),
        null
    );

    // Honeypot rules depend on config - wrap in try/catch for resilience
    try {
        $model = new \OPNsense\Lightscope\Lightscope();

        if ((string)$model->general->enabled != '1') {
            return;
        }

        $honeypot_ports = (string)$model->general->honeypot_ports;

        if (empty($honeypot_ports)) {
            return;
        }

        // Parse comma-separated ports
        $ports = array_map('trim', explode(',', $honeypot_ports));
        $valid_ports = array();

        foreach ($ports as $port) {
            if (is_numeric($port) && $port >= 1 && $port <= 65535) {
                $valid_ports[] = $port;
            }
        }

        if (empty($valid_ports)) {
            return;
        }

        // Create firewall rule to allow honeypot traffic
        $fw->registerFilterRule(
            100000,  // priority - run late so other rules take precedence
            array(
                'from' => 'any',
                'to' => '(self)',
                'direction' => 'in',
                'protocol' => 'tcp',
                'to_port' => implode(' ', $valid_ports),
                'descr' => 'LightScope honeypot ports',
                'log' => true,
                '#ref' => 'lightscope_honeypot'
            ),
            null
        );
    } catch (Exception $e) {
        // Model loading failed - log rule is still added, honeypot rules skipped
        // This ensures basic functionality even if config is broken
    }
}

function lightscope_xmlrpc_sync()
{
    $result = array();
    $result['id'] = 'lightscope';
    $result['section'] = 'OPNsense.Lightscope';
    $result['description'] = gettext('LightScope Security Monitor');
    $result['services'] = array('lightscope');
    return array($result);
}

function lightscope_configure()
{
    return array(
        'bootup' => array('lightscope_configure_do')
    );
}

function lightscope_configure_do($verbose = false)
{
    if (lightscope_enabled()) {
        if ($verbose) {
            echo 'Starting LightScope...';
        }
        configd_run('lightscope start');
        if ($verbose) {
            echo "done.\n";
        }
    }
}
