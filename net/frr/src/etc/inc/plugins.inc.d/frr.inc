<?php

/*
 * Copyright (C) 2026 Deciso B.V.
 * Copyright (C) 2018 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2017 Fabian Franz
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
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

function frr_enabled()
{
    $model = new \OPNsense\Quagga\General();
    return !$model->enabled->isEmpty();
}

function frr_carp_enabled()
{
    $model = new \OPNsense\Quagga\General();
    return !$model->enabled->isEmpty() && !$model->enablecarp->isEmpty();
}

function frr_configure()
{
    return [
        'frr_sync' => ['frr_configure_do'],
    ];
}

function frr_configure_do($verbose = false)
{
    $model = new \OPNsense\Quagga\General();
    if (!$model->manual_config->isEmpty()) {
        return;
    }
    (new \OPNsense\Core\Backend())->configdpRun('template reload', ['OPNsense/Quagga/Config']);
}

function frr_firewall($fw)
{
    global $config;
    if ((new \OPNsense\Quagga\General())->fwrules->isEmpty()) {
        // automatic rules disabled
        return;
    }
    if (!(new \OPNsense\Quagga\OSPF())->enabled->isEmpty()) {
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet',
                'protocol' => 'ospf',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass OSPF',
                'to' => '224.0.0.0/24',
                'direction' => 'in',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet',
                'protocol' => 'ospf',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass OSPF UNICAST',
                'to' => '(self)',
                'direction' => 'in',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet',
                'protocol' => 'ospf',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass OSPF',
                'from' => '(self)',
                'to' => '224.0.0.0/24',
                'direction' => 'out',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet',
                'protocol' => 'ospf',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass OSPF UNICAST',
                'from' => '(self)',
                'direction' => 'out',
            ]
        );
    }

    if (!(new \OPNsense\Quagga\OSPF6())->enabled->isEmpty()) {
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet6',
                'protocol' => 'ospf',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass OSPF6 MULTICAST',
                'from' => 'fe80::0/10',
                'to' => 'ff02::5/128',
                'direction' => 'in',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet6',
                'protocol' => 'ospf',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass OSPF6 MULTICAST DR',
                'from' => 'fe80::0/10',
                'to' => 'ff02::6/128',
                'direction' => 'in',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet6',
                'protocol' => 'ospf',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass OSPF6 UNICAST',
                'from' => 'fe80::0/10',
                'to' => '(self)',
                'direction' => 'in',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet6',
                'protocol' => 'ospf',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass OSPF6 MULTICAST',
                'from' => '(self)',
                'to' => 'ff02::5/128',
                'direction' => 'out',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet6',
                'protocol' => 'ospf',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass OSPF6 MULTICAST DR',
                'from' => '(self)',
                'to' => 'ff02::6/128',
                'direction' => 'out',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet6',
                'protocol' => 'ospf',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass OSPF6 UNICAST',
                'from' => '(self)',
                'to' => 'fe80::0/10',
                'direction' => 'out',
            ]
        );
    }

    if (!(new \OPNsense\Quagga\BGP())->enabled->isEmpty()) {
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet46',
                'protocol' => 'tcp',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass BGP',
                'to' => '(self)',
                'to_port' => 179,
                'direction' => 'in',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet46',
                'protocol' => 'tcp',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass BGP',
                'from' => '(self)',
                'to_port' => 179,
                'direction' => 'out',
            ]
        );
    }

    if (!(new \OPNsense\Quagga\BFD())->enabled->isEmpty()) {
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet46',
                'protocol' => 'udp',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass BFD',
                'to' => '(self)',
                'to_port' => 3784,
                'direction' => 'in',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet46',
                'protocol' => 'udp',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass BFD',
                'from' => '(self)',
                'to_port' => 3784,
                'direction' => 'out',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet46',
                'protocol' => 'udp',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass BFD MULTIHOP',
                'to' => '(self)',
                'to_port' => 4784,
                'direction' => 'in',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet46',
                'protocol' => 'udp',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass BFD MULTIHOP',
                'from' => '(self)',
                'to_port' => 4784,
                'direction' => 'out',
            ]
        );
    }

    if (!(new \OPNsense\Quagga\RIP())->enabled->isEmpty()) {
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet',
                'protocol' => 'udp',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass RIP',
                'to' => '(self),224.0.0.9,255.255.255.255',
                'to_port' => 520,
                'direction' => 'in',
            ]
        );
        $fw->registerFilterRule(
            1,
            [
                'ipprotocol' => 'inet',
                'protocol' => 'udp',
                'statetype' => 'keep',
                'type' => 'pass',
                'disablereplyto' => 1,
                'quick' => true,
                'log' => !isset($config['syslog']['nologdefaultpass']),
                '#ref' => 'ui/quagga/general/index',
                'descr' => 'Pass RIP',
                'from' => '(self)',
                'to_port' => 520,
                'direction' => 'out',
            ]
        );
    }
}

function frr_services()
{
    $services = array();

    if (frr_enabled()) {
        $services[] = array(
            'description' => gettext('FRRouting Daemon'),
            'configd' => array(
                'restart' => array('quagga restart'),
                'start' => array('quagga start'),
                'stop' => array('quagga stop'),
            ),
            'name' => 'frr',
            'pidfile' => '/var/run/frr/zebra.pid'
        );
    }

    return $services;
}

function frr_xmlrpc_sync()
{
    $result = array();
    $result['id'] = 'quagga';
    $result['section'] = 'OPNsense.quagga';
    $result['description'] = gettext('FRR');
    $result['services'] = ['frr'];
    return array($result);
}
