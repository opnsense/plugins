<?php

/*
 * Copyright (C) 2026 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the disclaimer in the documentation
 *    and/or other materials provided with the distribution.
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

namespace OPNsense\Quagga\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Quagga\OSPF;

class M1_1_5 extends BaseModelMigration
{
    public function run($model)
    {
        if (!$model instanceof OSPF) {
            return;
        }

        $config = Config::getInstance()->object();
        $passiveInterfaces = (string)$config->OPNsense->quagga->ospf->passiveinterfaces;
        if ($passiveInterfaces === '') {
            return;
        }

        // Reuse enabled interface records without activating dormant settings.
        $interfaces = $model->getNodeByReference('interfaces.interface');
        $interfacesByName = [];
        foreach ($interfaces->iterateItems() as $interface) {
            if ($interface->enabled->isEqual('1') && !$interface->interfacename->isEmpty()) {
                $interfacesByName[(string)$interface->interfacename] = $interface;
            }
        }

        // Move legacy passive interfaces into their interface records.
        foreach (explode(',', $passiveInterfaces) as $interfaceName) {
            $interfaceName = trim($interfaceName);
            if ($interfaceName === '') {
                continue;
            }

            if (!isset($interfacesByName[$interfaceName])) {
                $interface = $interfaces->add();
                $interface->enabled = '1';
                $interface->interfacename = $interfaceName;
                $interfacesByName[$interfaceName] = $interface;
            }
            $interfacesByName[$interfaceName]->passive = '1';
        }
    }
}
