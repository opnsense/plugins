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

namespace OPNsense\Bind\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Bind\General;

class M1_0_14 extends BaseModelMigration
{
    /**
     * Convert legacy explicit listener addresses to their configured interfaces.
     */
    public function run($model)
    {
        if (!($model instanceof General)) {
            return;
        }

        $config = Config::getInstance()->object();
        $general = $config->OPNsense->bind->general ?? null;
        if ($general === null || !empty($general->active_interface)) {
            return;
        }

        $addresses = array_filter(array_merge(
            explode(',', (string)$general->listenv4),
            explode(',', (string)$general->listenv6)
        ));
        if (in_array('0.0.0.0', $addresses, true) || in_array('::', $addresses, true)) {
            return;
        }

        $selected = array_fill_keys($addresses, true);
        $interfaces = [];
        foreach (interfaces_addresses(array_keys((array)$config->interfaces)) as $address => $info) {
            $address = explode('%', $address, 2)[0];
            if (isset($selected[$address]) && !empty($info['interface'])) {
                $interfaces[$info['interface']] = true;
            }
        }
        if (!empty($interfaces)) {
            $model->active_interface->setValue(implode(',', array_keys($interfaces)));
        }
    }
}
