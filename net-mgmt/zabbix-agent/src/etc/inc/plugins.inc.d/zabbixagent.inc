<?php

/**
 *    Copyright (C) 2017 Frank Wall
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
 */

function zabbixagent_enabled()
{
    global $config;

    return isset($config['OPNsense']['ZabbixAgent']['settings']['main']['enabled']) &&
    $config['OPNsense']['ZabbixAgent']['settings']['main']['enabled'] == 1;
}

function zabbixagent_firewall($fw)
{
    if (!zabbixagent_enabled()) {
        return;
    }

    $fw->registerAnchor('zabbix-agent/*', 'nat');
    $fw->registerAnchor('zabbix-agent/*', 'rdr');
    $fw->registerAnchor('zabbix-agent/*', 'fw');
}

/**
 *  register legacy service
 * @return array
 */
function zabbixagent_services()
{
    $services = array();

    if (!zabbixagent_enabled()) {
        return $services;
    }

    $services[] = array(
        'description' => gettext('Zabbix Agent'),
        'pidfile' => '/var/run/zabbix/zabbix_agentd.pid',
        'configd' => array(
            'restart' => array('zabbix_agentd restart'),
            'start' => array('zabbix_agentd start'),
            'stop' => array('zabbix_agentd stop'),
        ),
        'name' => 'zabbix_agentd',
    );

    return $services;
}

/**
 *  sync configuration via xmlrpc
 * @return array
 */

function zabbixagent_xmlrpc_sync()
{
    $result = array();
    $result['id'] = 'zabbixagent';
    $result['section'] = 'OPNsense.zabbixagent.settings';
    $result['description'] = gettext('Enterprise-class open source distributed monitoring agent');
    return array($result);
}
