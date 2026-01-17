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

        // Fixup the listen addresses to include the default port.
        $model->data_server->listen = implode(",", array_map(
            function ($ip) {
                if (Util::isIpv6Address($ip)) {
                    return "[" . $ip . "]:3493";
                } else {
                    return $ip . ":3493";
                }
            },
            explode(",", $nutConfig->general->listen)
        ));

        // Migrate the admin user.
        $adminUser = $model->user->add();
        $adminUser->username = "admin";
        $adminUser->password = $nutConfig->account->admin_password;
        $adminUser->actions = "set";
        $adminUser->instcmds = "all";
        // Migrate the monuser user.
        $monitorUser = $model->user->add();
        $monitorUser->username = "monuser";
        $monitorUser->password = $nutConfig->account->mon_password;
        $monitorUser->upsmon = "primary";

        // Migrate UPS/monitor definitions. Disabled UPSs' names are
        // suffixed with the driver to not conflict with the enabled UPS.
        // This should avoid breaking existing configurations. Technically
        // this can still result in conflicts between enabled UPSs, but
        // this would only happen if the configuration was broken before
        // the migration.
        $this->migrateGenericUps($model, $nutConfig, $monitorUser, "usbhid");
        $this->migrateGenericUps($model, $nutConfig, $monitorUser, "apcsmart");
        $this->migrateGenericUps($model, $nutConfig, $monitorUser, "bcmxcpusb");
        $this->migrateGenericUps($model, $nutConfig, $monitorUser, "blazerusb");
        $this->migrateGenericUps($model, $nutConfig, $monitorUser, "blazerser");
        $this->migrateGenericUps($model, $nutConfig, $monitorUser, "qx");
        $this->migrateGenericUps($model, $nutConfig, $monitorUser, "riello");
        $this->migrateGenericUps($model, $nutConfig, $monitorUser, "snmp");

        // apcupsd
        $ups = $model->drivers->ups->add();
        $ups->driver = "apcupsd";
        $ups->enabled = $nutConfig->apcupsd->enable;
        if (empty($nutConfig->apcupsd->port)) {
            $ups->options = "port=" . $nutConfig->apcupsd->hostname;
        } else {
            $ups->options = "port=" . $nutConfig->apcupsd->hostname . ":" . $nutConfig->apcupsd->port;
        }
        if ($ups->enabled == "1") {
            $ups->name = $nutConfig->general->name;
        } else {
            $ups->name = $nutConfig->general->name . "_" . $ups->driver;
        }
        $monitor = $model->monitoring->local->add();
        $monitor->ups = $ups->getAttributes()['uuid'];
        $monitor->user = $monitorUser->getAttributes()['uuid'];

        // netclient
        $monitor = $model->monitoring->remote->add();
        $monitor->enabled = $nutConfig->netclient->enable;
        $monitor->ups_name = $nutConfig->general->name;
        $monitor->hostname = $nutConfig->netclient->address;
        $monitor->port = $nutConfig->netclient->port;
        $monitor->username = $nutConfig->netclient->user;
        $monitor->password = $nutConfig->netclient->password;

        parent::run($model);
    }

    private function migrateGenericUps(
        $model,
        $nutConfig,
        $monitorUser,
        $driverName
    ) {
        $upsName = $nutConfig->general->name;
        $ups = $model->drivers->ups->add();
        $ups->driver = $driverName;
        $ups->enabled = $nutConfig->$driverName->enable;
        $ups->options = str_replace(",", ";", $nutConfig->$driverName->args);
        if ($ups->enabled == "1") {
            $ups->name = $upsName;
        } else {
            $ups->name = $upsName . "_" . $driverName;
        }
        $monitor = $model->monitoring->local->add();
        $monitor->enabled = $nutConfig->$driverName->enable;
        $monitor->ups = $ups->getAttributes()['uuid'];
        $monitor->user = $monitorUser->getAttributes()['uuid'];
        // Force the cached model data to refresh.
        $monitor->ups->getCachedData("OPNsense\\Nut\\Nut", "drivers.ups", true);
        $monitor->user->getCachedData("OPNsense\\Nut\\Nut", "user", true);
    }
}
