<?php

/*
 * Copyright (C) 2018 EURO-LOG AG
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

namespace OPNsense\Relayd\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M1_0_0 extends BaseModelMigration
{
    public function run($model)
    {
        $cfg = Config::getInstance();
        $cfgObj = $cfg->object();

        $hosts = array();
        $tableChecks = array();
        $tables = array();
        $protocols = array();
        $sticky = 0;

        /* load_balancer setting */
        if (!empty($cfgObj->load_balancer->setting->timeout)) {
            $model->general->timeout = $cfgObj->load_balancer->setting->timeout;
        }
        if (!empty($cfgObj->load_balancer->setting->interval)) {
            $model->general->interval = $cfgObj->load_balancer->setting->interval;
        }
        if (!empty($cfgObj->load_balancer->setting->prefork)) {
            $model->general->prefork = $cfgObj->load_balancer->setting->prefork;
        }
        if (!empty($cfgObj->load_balancer->setting->lb_use_sticky)) {
            $sticky = $cfgObj->load_balancer->setting->lb_use_sticky;
        }

        /* load_balancer monitor_type */
        if (!empty($cfgObj->load_balancer->monitor_type) && count($cfgObj->load_balancer->monitor_type)) {
            foreach ($cfgObj->load_balancer->monitor_type as $monitorType) {
                if (!empty($monitorType->name)) {
                    $name = $monitorType->name->__toString();
                    switch ($monitorType->type) {
                        case 'http':
                        case 'https':
                            if (
                                !empty($monitorType->options->path) &&
                                !empty($monitorType->options->code)
                            ) {
                                $setting = array(
                                    'name' => $name,
                                    'path' => $monitorType->options->path,
                                    'code' => $monitorType->options->code,
                                    'type' => 'http');
                                if ($monitorType->type == 'https') {
                                    $setting['ssl'] = 1;
                                }
                                if (!empty($monitorType->options->host)) {
                                    $setting['host'] = $monitorType->options->host;
                                }
                                if (!empty($setting)) {
                                    $node = $model->tablecheck->Add();
                                    $node->setNodes($setting);
                                    $tableChecks[$name]['uuid'] = $node->getAttributes()['uuid'];
                                }
                            }
                            break;
                        case 'send':
                            if (!empty($monitorType->options->expect)) {
                                $setting = array(
                                    'name' => $name,
                                    'type' => 'send',
                                    'expect' => $monitorType->options->expect);
                            }
                            if (!empty($monitorType->options->data)) {
                                $setting['data'] = $monitorType->options->data;
                            }
                            if (!empty($setting)) {
                                $node = $model->tablecheck->Add();
                                $node->setNodes($setting);
                                $tableChecks[$name]['uuid'] = $node->getAttributes()['uuid'];
                            }
                            break;
                        default:
                            $node = $model->tablecheck->Add();
                            $node->setNodes(array('name' => $name, 'type' => $monitorType->type));
                            $tableChecks[$name]['uuid'] = $node->getAttributes()['uuid'];
                            break;
                    }
                }
            }
        }

        /* load_balancer lbpool */
        if (!empty($cfgObj->load_balancer->lbpool) && count($cfgObj->load_balancer->lbpool)) {
            foreach ($cfgObj->load_balancer->lbpool as $lbpool) {
                if (
                    !empty($lbpool->name) &&
                    !empty($lbpool->servers &&
                    !empty($lbpool->monitor))
                ) {
                    $name = $lbpool->name->__toString();
                    $tableSetting = array(
                        'enabled' => 1,
                        'name' => $lbpool->name,
                        'hosts' => ''
                    );
                    /* cannot import 'serversdisabled' */
                    foreach ($lbpool->servers as $server) {
                        $serverName = $server->__toString();
                        /* add new host */
                        if (empty($hosts) || !isset($hosts[$serverName])) {
                            $hostSetting = array(
                                'name' => $serverName,
                                'address' => $serverName);
                            if (!empty($lbpool->retry)) {
                                $hostSetting['retry'] = $lbpool->retry;
                            }
                            $hostNode = $model->host->Add();
                            $hostNode->setNodes($hostSetting);
                            $hosts[$serverName]['uuid'] = $hostNode->getAttributes()['uuid'];
                        }
                        $tableSetting['hosts'] .= $hosts[$serverName]['uuid'] . ',';
                    }
                    $tableSetting['hosts'] = rtrim($tableSetting['hosts'], ',');
                    $tableNode = $model->table->Add();
                    $tableNode->setNodes($tableSetting);
                    $tables[$name]['uuid'] = $tableNode->getAttributes()['uuid'];
                    if (!empty($lbpool->mode) && $lbpool->mode == 'loadbalance') {
                        $tables[$name]['mode'] = 'loadbalance';
                    }
                    $monitor = $lbpool->monitor->__toString();
                    $tables[$name]['monitor'] = $tableChecks[$monitor]['uuid'];
                    if (!empty($lbpool->port)) {
                        $tables[$name]['port'] = $lbpool->port;
                    }
                }
            }
        }

        /* protocol definitions */
        $protocolDir = '/usr/local/etc/inc/plugins.inc.d/relayd';
        if (is_dir($protocolDir)) {
            $protocolFiles = glob($protocolDir . '/*.proto');
            foreach ($protocolFiles as $protocolFile) {
                $content = file_get_contents($protocolFile);
                preg_match('/^([^\{]*)\{((.|\n|\r)*)\}((\s|\n|\r)*)$/', $content, $acontent);
                if (preg_match('/^protocol\s+/', trim($acontent[1]))) {
                    preg_match('/^([^\s]*)\s+([^\s]*)/', trim($acontent[1]), $protocol);
                } else {
                    preg_match('/^([^\s]*)\s+([^\s]*)\s+([^\s]*)/', trim($acontent[1]), $protocol);
                }
                $type = trim($protocol[1]);
                if (count($protocol) == 3 && $type == 'protocol') {
                    $type = 'tcp';
                    $name = trim($protocol[2]);
                } else {
                    $name = trim($protocol[3]);
                }
                $name = trim($name, '"');
                $protocolSetting = array(
                    'name' => $name,
                    'type' => $type,
                    'options' => trim($acontent[2])
                );
                $protocolNode = $model->protocol->Add();
                $protocolNode->setNodes($protocolSetting);
                $protocols[$name]['uuid'] = $protocolNode->getAttributes()['uuid'];
            }
        }

        /* load_balancer virtual_server */
        if (!empty($cfgObj->load_balancer->virtual_server) && count($cfgObj->load_balancer->virtual_server)) {
            foreach ($cfgObj->load_balancer->virtual_server as $virtual_server) {
                if (
                    !empty($virtual_server->name) &&
                    !empty($virtual_server->ipaddr) &&
                    !empty($virtual_server->port) &&
                    !empty($virtual_server->poolname)
                ) {
                    $poolname = $virtual_server->poolname->__toString();
                    $vserverSetting = array(
                        'enabled' => 1,
                        'name' => $virtual_server->name,
                        'listen_address' => $virtual_server->ipaddr,
                        'listen_startport' => $virtual_server->port,
                        'transport_type' => 'forward',
                        'transport_table' => $tables[$poolname]['uuid'],
                        'transport_tablecheck' => $tables[$poolname]['monitor']
                    );
                    if (!empty($virtual_server->mode)) {
                        $vserverSetting['type'] = $virtual_server->mode;
                        if (!empty($sticky) && $vserverSetting['type'] == 'redirect') {
                            $vserverSetting['stickyaddress'] = 1;
                        }
                    }
                    if (!empty($tables[$poolname]['port'])) {
                        $vserverSetting['transport_port'] = $tables[$poolname]['port'];
                    }
                    if (!empty($tables[$poolname]['mode'])) {
                        $vserverSetting['transport_tablemode'] = $tables[$poolname]['mode'];
                    }
                    if (!empty($virtual_server->sitedown)) {
                        $sitedown = $virtual_server->sitedown->__toString();
                        $vserverSetting['backuptransport_table'] = $tables[$sitedown]['uuid'];
                        $vserverSetting['backuptransport_tablecheck'] = $vserverSetting['transport_tablecheck'];
                        if (!empty($vserverSetting['transport_tablemode'])) {
                            $vserverSetting['backuptransport_tablemode'] = $vserverSetting['transport_tablemode'];
                        }
                    }
                    if (!empty($virtual_server->sessiontimeout)) {
                        $vserverSetting['sessiontimeout'] = $virtual_server->sessiontimeout;
                    }
                    if (!empty($virtual_server->relay_protocol)) {
                        $relay_protocol = $virtual_server->relay_protocol->__toString();
                        $vserverSetting['protocol'] = $protocols[$relay_protocol]['uuid'];
                    }
                    $vserverNode = $model->virtualserver->Add();
                    $vserverNode->setNodes($vserverSetting);
                }
            }
        }
    }
}
