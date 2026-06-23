<?php

/*
 * Copyright (C) 2022 Robbert Rijkse
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

namespace OPNsense\Bind\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Bind\Domain;

class M1_1_0 extends BaseModelMigration
{
    /**
    * Migrate older keys into new model
    * @param $model
    */
    public function run($model)
    {
        if ($model instanceof Domain) {
            $config = Config::getInstance()->object();

            /* checks to see if there is a bind config section, otherwise skips the rest of the migration */
            if (empty($config->OPNsense->bind)) {
                return;
            }

            $bindConfig = $config->OPNsense->bind;

            /* loops through the domains in the config */
            foreach ($bindConfig->domain->domains->domain as $domain) {
                $domainModel = $model->getNodeByReference('domains.domain.' . $domain->attributes()['uuid']);

                /* migrates the domain type */
                if ($domain->type == 'master') {
                    $domainModel->type->setValue('primary');
                } else {
                    $domainModel->type->setValue('secondary');
                }

                /* migrates the Master IP to Primary IP field */
                if (!empty($domain->masterip)) {
                    $domainModel->primaryip->setValue($domain->masterip);
                }

                /* migrates the AllowNotify Slave to AllowNotify Secondary field */
                if (!empty($domain->allownotifyslave)) {
                    $domainModel->allownotifysecondary->setValue($domain->allownotifyslave);
                }
            }

            parent::run($model);
        }
    }
}
