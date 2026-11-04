#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2025 OPNsense Community
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

/**
 * Create/remove optional interface xproxytun + gateway XPROXY_TUN for policy routing.
 * Uses a single Config::save() to avoid re-entrancy / lock contention from chained saves.
 */

@include_once('config.inc');

use OPNsense\Core\Config;
use OPNsense\Routing\Gateways;
use OPNsense\Xproxy\Xproxy;

const XPROXY_IFKEY = 'xproxytun';
const XPROXY_GWNAME = 'XPROXY_TUN';
const XPROXY_MARK = 'Xproxy (plugin)';

function xproxy_remove_gateway_mvc(): void
{
    $mdl = new Gateways();
    $uuid = null;
    foreach ($mdl->gateway_item->iterateItems() as $item) {
        if ((string)$item->name === XPROXY_GWNAME) {
            $uuid = (string)$item->getAttributes()['uuid'];
            break;
        }
    }
    if ($uuid !== null) {
        $mdl->gateway_item->del($uuid);
        $mdl->serializeToConfig();
    }
}

function xproxy_disable_interface_xml(): void
{
    $cfg = Config::getInstance()->object();
    if (empty($cfg->interfaces->{XPROXY_IFKEY})) {
        return;
    }
    $cfg->interfaces->{XPROXY_IFKEY}->enable = '0';
}

function xproxy_ensure_interface_xml(string $tunDev): void
{
    $cfg = Config::getInstance()->object();
    if (empty($cfg->interfaces)) {
        return;
    }
    $ifn = XPROXY_IFKEY;
    if (empty($cfg->interfaces->$ifn)) {
        $node = $cfg->interfaces->addChild($ifn);
        $node->addChild('enable', '1');
        $node->addChild('if', $tunDev);
        $node->addChild('descr', XPROXY_MARK);
        $node->addChild('ipaddr', 'none');
    } else {
        $cfg->interfaces->$ifn->enable = '1';
        $cfg->interfaces->$ifn->if = $tunDev;
        if (empty((string)$cfg->interfaces->$ifn->descr)) {
            $cfg->interfaces->$ifn->descr = XPROXY_MARK;
        }
        if (empty((string)$cfg->interfaces->$ifn->ipaddr)) {
            $cfg->interfaces->$ifn->ipaddr = 'none';
        }
    }
}

function xproxy_ensure_gateway_mvc(string $tunGw): void
{
    $mdl = new Gateways();
    $uuid = null;
    foreach ($mdl->gateway_item->iterateItems() as $item) {
        if ((string)$item->name === XPROXY_GWNAME) {
            $uuid = (string)$item->getAttributes()['uuid'];
            break;
        }
    }
    $fields = [
        'name' => XPROXY_GWNAME,
        'interface' => XPROXY_IFKEY,
        'ipprotocol' => 'inet',
        'gateway' => $tunGw,
        'descr' => XPROXY_MARK,
        'defaultgw' => '0',
        'monitor_disable' => '1',
        'priority' => '255',
    ];
    $mdl->createOrUpdateGateway($fields, $uuid);
    $mdl->serializeToConfig();
}

$xp = new Xproxy();
$enabled = (string)$xp->general->enabled === '1';
/* Default on when unset (older configs): only explicit "0" disables */
$policy = (string)$xp->general->policy_route_lan !== '0';
$tunDev = (string)$xp->general->tun_device;
if ($tunDev === '') {
    $tunDev = 'tun9';
}
$tunGw = (string)$xp->general->tun_gateway;
if ($tunGw === '') {
    $tunGw = '10.255.0.2';
}

if (!$enabled || !$policy) {
    xproxy_remove_gateway_mvc();
    xproxy_disable_interface_xml();
    Config::getInstance()->save();
    exit(0);
}

if (!preg_match('/^tun[0-9]{1,3}$/', $tunDev) || filter_var($tunGw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
    syslog(LOG_ERR, 'xproxy sync_gateway: invalid tun device or gateway, aborting gateway sync');
    exit(1);
}

xproxy_ensure_interface_xml($tunDev);
xproxy_ensure_gateway_mvc($tunGw);
Config::getInstance()->save();
exit(0);
