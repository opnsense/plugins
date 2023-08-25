<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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


function wazuhagent_services()
{
    $services = [];
    if ((new \OPNsense\WazuhAgent\WazuhAgent())->general->enabled == '1') {
        $service = [
            'description' => gettext('Wazuh Agent'),
            'configd' => [
                'restart' => ['wazuh_agent restart'],
                'start' => ['wazuh_agent start'],
                'stop' => ['wazuh_agent stop'],
            ],
            'name' => 'wazuh-agentd',
        ];
        $services[] = $service;
    }

    return $services;
}

function wazuhagent_firewall($fw)
{
    global $config;
    $defaults = ['block' => ['type' => 'block', 'log' => !isset($config['syslog']['nologdefaultblock'])]];
    if ((new \OPNsense\WazuhAgent\WazuhAgent())->general->enabled == '1') {
        //$fw->registerFilterRule();
        $fw->registerFilterRule(
            1,
            ['from' => '<__wazuh_agent_drop>', 'descr' => 'Wazuh agent blocklist', '#ref' => 'ui/wazuhagent/'],
            $defaults['block']
        );
    }
}
