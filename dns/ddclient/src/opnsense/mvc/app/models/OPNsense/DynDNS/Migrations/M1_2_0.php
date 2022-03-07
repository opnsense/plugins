<?php

/*
 * Copyright (C) 2021 Deciso B.V.
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
use OPNsense\Base\FieldTypes\BooleanField;
use OPNsense\Base\FieldTypes\NetworkField;
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

        $globalsettings = $model->OPNsense->DynDNS->general;
        $accounts = $model->OPNsense->DynDNS->accounts;

        foreach ($accounts as $account) {
            // migrate checkip value into each account as it was previously a global setting
            if (!empty($globalsettings->checkip)) {
                $account->setAttributeValue('checkip', $globalsettings->checkip);
            } else {
                $account->setAttributeValue('checkip', 'if');
            }

            //migrate the interface value into each account unless it was already set by use_interface
            if (empty($account->interface)) {
                $account->setAttributeValue('interface', $globalsettings->interface);
            }

            // set the checkipscheme
            $account->setAttributeValue('checkipscheme', 'https'); 
            
            //some checkip providers might have been used with http, we keep that here unless forcessl was used
            $providersusinghttp = array("web_dyndns", "web_he", "web_ip4only.me", "web_ip6only.me", "web_noip-ipv4", "web_noip-ipv6", "web_zoneedit");
            if($globalsettings->force_ssl==0) {
                $account->setAttributeValue('checkipscheme', 'http'); 
            }

        }
    }
}
