<?php

/**
 *    Copyright (C) 2019 Frank Wall
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\AcmeClient\Migrations;

use OPNsense\Base\BaseModelMigration;

class M2_0_0 extends BaseModelMigration
{
    public function run($model)
    {
        // Search accounts
        foreach ($model->getNodeByReference('accounts.account')->iterateItems() as $account) {
            if (!empty((string)$account->lastUpdate) && empty((string)$account->statusLastUpdate)) {
                $account->statusLastUpdate = (string)$account->lastUpdate;
                // Account is already registered.
                $account->statusCode = '200';
                $account->lastUpdate = null; // clear old value
            } elseif (!empty((string)$account->statusLastUpdate) || !empty((string)$account->statusCode)) {
                // Ignore accounts that already use M2_0_0 fields.
            } else {
                // Account registration is pending.
                $account->statusCode = '100';
            }
        }
    }
}
