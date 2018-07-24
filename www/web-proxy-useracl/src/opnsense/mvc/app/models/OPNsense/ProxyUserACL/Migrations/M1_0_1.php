<?php

/**
 *    Copyright (C) 2017-2018 Smart-Soft
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

namespace OPNsense\ProxyUserACL\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;


class M1_0_1 extends BaseModelMigration
{
    public function run($model)
    {
        parent::run($model);

        foreach (Config::getInstance()->object()->OPNsense->ProxyUserACL->general->ACLs->ACL as $acl) {

            $user = $model->general->Users->User->add();
            $user->Names = $acl->Name->__toString();
            $user->Hex = $acl->Hex->__toString();
            $user->Group = $acl->Group->__toString();
            $user->Server = $acl->Server->__toString();

            $domain = $model->general->Domains->Domain->add();
            $domain->Names = $acl->Domains->__toString();

            $new_acl = $model->general->HTTPAccesses->HTTPAccess->add();
            $new_acl->Users = $user->getAttributes()["uuid"];
            $new_acl->Domains = $domain->getAttributes()["uuid"];

            unset($acl);
        }

        $model->serializeToConfig();
        Config::getInstance()->save();
    }
}
