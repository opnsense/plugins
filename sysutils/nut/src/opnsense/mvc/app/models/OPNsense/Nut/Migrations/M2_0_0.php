<?php

/*
 * Copyright (C) 2026 Gabriel Smith <ga29smith@gmail.com>
 *
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
use OPNsense\Firewall\Util;

class M2_0_0 extends BaseModelMigration
{
    /**
    * Migrate older keys into new model
    * @param $model
    */
    public function run($model)
    {
        $config = Config::getInstance()->object();

        if (empty($config->OPNsense->Nut)) {
            return;
        }

        $nutConfig = $config->OPNsense->Nut;

        // netclient isn't a local UPS so it can't be migrated as such, but
        // still needs a UPS name.
        $model->netclient->name = $nutConfig->general->name;

        // Migrate UPS/monitor definitions. Disabled UPSs' names are
        // suffixed with the driver to not conflict with the enabled UPS.
        // This should avoid breaking existing configurations. Technically
        // this can still result in conflicts between enabled UPSs, but
        // this would only happen if the configuration was broken before
        // the migration.
        $this->migrateGenericUps($model, $nutConfig, "usbhid");
        $this->migrateGenericUps($model, $nutConfig, "apcsmart");
        $this->migrateGenericUps($model, $nutConfig, "bcmxcpusb");
        $this->migrateGenericUps($model, $nutConfig, "blazerusb");
        $this->migrateGenericUps($model, $nutConfig, "blazerser");
        $this->migrateGenericUps($model, $nutConfig, "qx");
        $this->migrateGenericUps($model, $nutConfig, "riello");
        $this->migrateGenericUps($model, $nutConfig, "snmp");

        // apcupsd
        $ups = $model->drivers->ups->add();
        $ups->driver = "apcupsd";
        $ups->enabled = $nutConfig->apcupsd->enable;
        if (empty($nutConfig->apcupsd->port)) {
            $ups->port = $nutConfig->apcupsd->hostname;
        } else {
            $ups->port = $nutConfig->apcupsd->hostname . ":"
                . $nutConfig->apcupsd->port;
        }
        if ($ups->enabled == "1") {
            $ups->name = $nutConfig->general->name;
        } else {
            $ups->name = $nutConfig->general->name . "_" . $ups->driver;
        }

        parent::run($model);
    }

    private function migrateGenericUps($model, $nutConfig, $driverName)
    {
        $upsName = $nutConfig->general->name;
        $ups = $model->drivers->ups->add();
        $ups->driver = $driverName;
        $ups->enabled = $nutConfig->$driverName->enable;
        if ($ups->enabled == "1") {
            $ups->name = $upsName;
        } else {
            $ups->name = $upsName . "_" . $driverName;
        }
        $options = explode(",", $nutConfig->$driverName->args);
        $ports = array_map(
            function ($o) {
                return str_replace("port=", "", $o);
            },
            array_filter(
                $options,
                function ($o) {
                    return str_starts_with($o, "port=");
                }
            )
        );
        if (empty($ports)) {
            $ups->port = "auto";
        } else {
            $ups->port = $ports[array_key_last($ports)];
        }
        $ups->options = implode(
            ";",
            array_filter(
                $options,
                function ($o) {
                    return !str_starts_with($o, "port=");
                }
            )
        );
    }
}
