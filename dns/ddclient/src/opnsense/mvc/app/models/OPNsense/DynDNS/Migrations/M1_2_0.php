<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\DynDNS\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M1_2_0 extends BaseModelMigration
{
    /**
     * Migrate older models into shared model
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();

        if (empty($config->OPNsense->DynDNS)) {
            return;
        }

        // migration will move these settings, extract datapoints from raw config
        $checkip =  (string)$config->OPNsense->DynDNS->general->checkip;
        $interface = $checkip == "if" ? (string)$config->OPNsense->DynDNS->general->interface : "";
        $force_ssl = (string)$config->OPNsense->DynDNS->general->force_ssl;
        $pre_account = [];
        if (!empty($config->OPNsense->DynDNS->accounts->account)) {
            foreach ($config->OPNsense->DynDNS->accounts->account as $account) {
                $pre_account[(string)$account->attributes()['uuid']] = [
                    "checkip" => !empty($account->use_interface) ? "if" : $checkip,
                    "interface" => !empty($account->use_interface) ? (string)$account->interface : $interface
                ];
            }
        }

        // update accounts
        foreach ($model->accounts->account->iterateItems() as $account) {
            $uuid =  $account->getAttributes()['uuid'];
            $account->checkip = $pre_account[$uuid]['checkip'];
            $account->interface = $pre_account[$uuid]['interface'];
            $account->force_ssl = $force_ssl;
        }
    }
}
