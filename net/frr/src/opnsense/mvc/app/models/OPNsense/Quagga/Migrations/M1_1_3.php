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

class M1_1_3 extends BaseModelMigration
{
    public function run($model)
    {
        $neighbors = $model->getNodeByReference('neighbors.neighbor');

        if ($neighbors === null) {
            return;
        }

        $config = Config::getInstance()->object();

        if (empty($config->OPNsense->quagga->bgp->neighbors->neighbor)) {
            return;
        }

        foreach ($neighbors->iterateItems() as $uuid => $neighbor) {
            $config_neighbor = null;
            // Could be a lookup table but a migration is one shot anyway
            foreach ($config->OPNsense->quagga->bgp->neighbors->neighbor as $candidate) {
                if ((string)$candidate['uuid'] === (string)$uuid) {
                    $config_neighbor = $candidate;
                    break;
                }
            }

            if ($config_neighbor === null || isset($config_neighbor->family)) {
                continue;
            }

            if ((string)$config_neighbor->multiprotocol === '1') {
                $neighbor->family = 'ipv4,ipv6';
            } elseif (strpos((string)$config_neighbor->address, ':') !== false) {
                $neighbor->family = 'ipv6';
            } else {
                $neighbor->family = 'ipv4';
            }
        }
    }

    // Model is saved by 'run_migrations.php'
}
