<?php

// SPDX-License-Identifier: MIT
// SPDX-FileCopyrightText: © 2021 CrowdSec <info@crowdsec.net>

use OPNsense\Core\Config;
use OPNsense\Firewall\Alias;
use OPNsense\Firewall\Plugin;

function add_alias_if_not_exist($name, $description, $proto)
{
    $model = new Alias();

    if ($model->getByName($name) != null) {
        return;
    }

    $new_alias = $model->aliases->alias->Add();
    $new_alias->name = $name;
    $new_alias->description = $description;
    $new_alias->proto = $proto;
    $new_alias->type = 'external';
    $model->serializeToConfig();
    Config::getInstance()->save();
}

function crowdsec_firewall(Plugin $fw)
{
    global $config;

    $general = $config['OPNsense']['crowdsec']['general'];

    $bouncer_enabled = isset($general['firewall_bouncer_enabled']) && $general['firewall_bouncer_enabled'];

    if (!$bouncer_enabled) {
        return;
    }

    $rules_log_enabled = isset($general['rules_log']) && $general['rules_log'];

    $rules_tag = "";
    if (isset($general['rules_tag'])) {
        $rules_tag = $general['rules_tag'];
    }

    add_alias_if_not_exist('crowdsec_blocklists', 'CrowdSec (IPv4)', 'IPv4');
    add_alias_if_not_exist('crowdsec6_blocklists', 'CrowdSec (IPv6)', 'IPv6');

    // https://github.com/opnsense/core/blob/master/src/opnsense/mvc/app/library/OPNsense/Firewall/FilterRule.php

    // if missing, default to true
    if (!isset($general['rules_enabled']) || $general['rules_enabled'] != 0) {
        $fw->registerFilterRule(
            1, /* priority */
            array(
                'ipprotocol' => 'inet',
                'descr'      => 'CrowdSec (IPv4) in',
                'from'       => '<crowdsec_blocklists>',
                'direction'  => 'in',
                'type'       => 'block',
                'log'        => $rules_log_enabled,
                'tag'        => $rules_tag,
                'quick'      => true
            )
        );

        $fw->registerFilterRule(
            1, /* priority */
            array(
                'ipprotocol' => 'inet',
                'descr'      => 'CrowdSec (IPv4) out',
                'to'         => '<crowdsec_blocklists>',
                'direction'  => 'out',
                'type'       => 'block',
                'log'        => $rules_log_enabled,
                'tag'        => $rules_tag,
                'quick'      => true
            )
        );

        $fw->registerFilterRule(
            1, /* priority */
            array(
                'ipprotocol' => 'inet6',
                'descr'      => 'CrowdSec (IPv6) in',
                'from'       => '<crowdsec6_blocklists>',
                'direction'  => 'in',
                'type'       => 'block',
                'log'        => $rules_log_enabled,
                'tag'        => $rules_tag,
                'quick'      => true
            )
        );

        $fw->registerFilterRule(
            1, /* priority */
            array(
                'ipprotocol' => 'inet6',
                'descr'      => 'CrowdSec (IPv6) out',
                'to'         => '<crowdsec6_blocklists>',
                'direction'  => 'out',
                'type'       => 'block',
                'log'        => $rules_log_enabled,
                'tag'        => $rules_tag,
                'quick'      => true
            )
        );
    }
}

function crowdsec_services()
{
    $services[] = array(
        'description' => 'CrowdSec',
        'configd' => array(
            'restart' => array('crowdsec restart'),
            'start'   => array('crowdsec start'),
            'stop'    => array('crowdsec stop'),
        ),
        'name' => 'crowdsec'
    );

    return $services;
}
