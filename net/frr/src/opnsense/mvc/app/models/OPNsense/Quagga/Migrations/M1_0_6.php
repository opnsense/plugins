<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Quagga\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M1_0_6 extends BaseModelMigration
{
    public function run($model)
    {
        $config = Config::getInstance()->object();

        if (!isset($config->OPNsense->quagga->ospf)) {
            return;
        }
        
        if (!empty($config->OPNsense->quagga->ospf->passiveinterfaces)) {
            $existingInterfaces = [];
            foreach ($model->getNodeByReference('interfaces.interface')->iterateItems() as $interface) {
                $existingInterfaces[(string)$interface->interfacename] = $interface;
            }

            $passiveInterfacesList = explode(',', (string)$config->OPNsense->quagga->ospf->passiveinterfaces);
            foreach ($passiveInterfacesList as $passiveInterfaceName) {
                $passiveInterfaceName = trim($passiveInterfaceName);
                if (empty($passiveInterfaceName)) {
                    continue;
                }

                if (isset($existingInterfaces[$passiveInterfaceName])) {
                    $existingInterfaces[$passiveInterfaceName]->passive = '1';
                } else {
                    $newInterface = $model->getNodeByReference('interfaces.interface')->add();
                    $newInterface->interfacename = $passiveInterfaceName;
                    $newInterface->passive = '1';
                }
            }
        }
    }
}
