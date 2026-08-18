<?php

/*
 * Copyright (C) 2026 Tore Amundsen <tore@amundsen.org>
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

namespace OPNsense\Nut\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M1_1_0 extends BaseModelMigration
{
    /**
     * Convert the old single UPS driver sections into a list of UPS devices.
     * All driver sections shared the same UPS name before, so rows after the
     * first get the section name appended to keep names unique.
     */
    public function run($model)
    {
        $cfgObj = Config::getInstance()->object();
        if (empty($cfgObj->OPNsense) || empty($cfgObj->OPNsense->Nut)) {
            return;
        }
        $nut = $cfgObj->OPNsense->Nut;
        $name = 'UPSName';
        if (!empty($nut->general) && !empty((string)$nut->general->name)) {
            $name = (string)$nut->general->name;
        }

        /* the netclient section keeps its own UPS name now */
        $model->netclient->name = $name;

        $drivers = array(
            'usbhid' => 'usbhid-ups',
            'apcsmart' => 'apcsmart',
            'apcupsd' => 'apcupsd-ups',
            'bcmxcpusb' => 'bcmxcp_usb',
            'blazerusb' => 'blazer_usb',
            'blazerser' => 'blazer_ser',
            'qx' => 'nutdrv_qx',
            'riello' => 'riello_usb',
            'snmp' => 'snmp-ups',
        );
        $first = true;
        foreach ($drivers as $section => $driver) {
            if (empty($nut->$section) || (string)$nut->$section->enable != '1') {
                continue;
            }
            if ($section == 'apcupsd') {
                $args = 'port=' . (string)$nut->$section->hostname;
                if (!empty((string)$nut->$section->port)) {
                    $args .= ':' . (string)$nut->$section->port;
                }
            } else {
                $args = (string)$nut->$section->args;
            }
            $ups = $model->upses->ups->Add();
            $ups->setNodes(array(
                'enabled' => 1,
                'name' => $first ? $name : $name . '-' . $section,
                'driver' => $driver,
                'args' => $args,
            ));
            $first = false;
        }
    }
}
