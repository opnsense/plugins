<?php

/**
 *    Copyright (C) 2022 Juergen Kellerer
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

class M3_3_0 extends BaseModelMigration
{
    public function run($model)
    {
        // Migrate validations
        foreach ($model->getNodeByReference('validations.validation')->iterateItems() as $validation) {
            $updateUrl = trim(strval($validation->dns_acmedns_updateurl ?? ''));
            $baseUrl = trim(strval($validation->dns_acmedns_baseurl ?? ''));

            if (!empty($updateUrl) && empty($baseUrl)) {
                // Translate "https://auth.acme-dns.io/update" to "https://auth.acme-dns.io"
                $baseUrl = preg_replace('/\/update$/', '', $updateUrl);
                $validation->dns_acmedns_baseurl = $baseUrl;
                $validation->dns_acmedns_updateurl = null;
            }
        }
    }
}
