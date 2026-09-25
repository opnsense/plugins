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
use OPNsense\Quagga\OSPF6;

class M1_1_4 extends BaseModelMigration
{
    public function run($model)
    {
        if ($model instanceof OSPF) {
            $protocol = 'ospf';
        } elseif ($model instanceof OSPF6) {
            $protocol = 'ospf6';
        } else {
            return;
        }

        $config = Config::getInstance()->object();
        $areas = $model->getNodeByReference('areas.area');
        $areasById = [];
        foreach ($areas->iterateItems() as $area) {
            $areasById[(string)$area->id] = $area;
        }

        // Move legacy area settings into explicit area records.
        if (!empty($config->OPNsense->quagga->{$protocol}->networks->network)) {
            foreach ($config->OPNsense->quagga->{$protocol}->networks->network as $network) {
                $areaId = (string)$network->area;
                $range = (string)$network->arearange;
                $prefixlistIn = (string)$network->linkedPrefixlistIn;
                $prefixlistOut = (string)$network->linkedPrefixlistOut;

                if (
                    $areaId === '' ||
                    ($range === '' && $prefixlistIn === '' && $prefixlistOut === '')
                ) {
                    continue;
                }

                $area = $areasById[$areaId] ?? null;
                if ($area === null) {
                    $area = $areas->add();
                    $area->enabled = '1';
                    $area->id = $areaId;
                    $areasById[$areaId] = $area;
                }

                if ($range !== '') {
                    $ranges = $area->ranges->getValues();
                    $ranges[] = $range;
                    $area->ranges->setValues(array_unique($ranges));
                }
                if ($prefixlistIn !== '') {
                    $area->linkedPrefixlistIn = $prefixlistIn;
                }
                if ($prefixlistOut !== '') {
                    $area->linkedPrefixlistOut = $prefixlistOut;
                }
            }
        }

        // Replace legacy area IDs with references to the explicit area records.
        foreach (['networks.network', 'interfaces.interface'] as $reference) {
            foreach ($model->getNodeByReference($reference)->iterateItems() as $item) {
                if ($item->area->isEmpty()) {
                    continue;
                }
                $areaId = (string)$item->area;

                if (!isset($areasById[$areaId])) {
                    $area = $areas->add();
                    $area->enabled = '1';
                    $area->id = $areaId;
                    $areasById[$areaId] = $area;
                } elseif (!$areasById[$areaId]->enabled->isEqual('1')) {
                    // Disabled legacy areas only suppressed their special area type.
                    $areasById[$areaId]->enabled = '1';
                    if ($model instanceof OSPF) {
                        $areasById[$areaId]->type = '';
                    }
                }
                $item->area = $areasById[$areaId]->getAttribute('uuid');
                // Relation options still reflect the model state from before this migration.
                $item->area->markUnchanged();
            }
        }
    }
}
