<?php
/**
 *    Copyright (C) 2018 Frank Wall
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
namespace OPNsense\HAProxy\Migrations;

use OPNsense\Base\BaseModelMigration;

class M2_6_0 extends BaseModelMigration
{
    public function run($model)
    {
        // Migrate old stats user:password entries to new user management feature
        if (!empty((string)$model->general->stats->users)) {
            // Add new user for each entry
            $UUIDlist = array();
            foreach (explode(',', (string)$model->general->stats->users) as $statsuser) {
                $olddata = explode(':', $statsuser, 2);
                $userNode = $model->users->user->Add();
                $userNode->name = (string)$olddata[0];
                $userNode->description = 'stats user';
                $userNode->password = (string)$olddata[1];
                $userNode->enabled = 1;
                $UUIDlist[] = $userNode->getAttributes()['uuid'];
            }

            // Add collected UUIDs to new list of allowed users
            $model->general->stats->allowedUsers = (string)implode(',', $UUIDlist);
        }
    }
}
