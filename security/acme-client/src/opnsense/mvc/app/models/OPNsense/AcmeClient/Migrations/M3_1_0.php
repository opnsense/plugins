<?php

/**
 *    Copyright (C) 2021 Frank Wall
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

class M3_1_0 extends BaseModelMigration
{
    public function run($model)
    {
        // Search actions
        foreach ($model->getNodeByReference('actions.action')->iterateItems() as $action) {
            // Field "configd" was renamed to "configd_generic_command"
            if (!empty((string)$action->configd)) {
                $action->configd_generic_command = (string)$action->configd;
                $action->configd = null; // clear old value
            }

            // Get old type and map to new value
            $old_type = (string)$action->type;
            switch ($old_type) {
                case 'configd':
                    $new_type = 'configd_generic';
                    break;
                case 'restart_gui':
                    $new_type = 'configd_restart_gui';
                    break;
                case 'restart_haproxy':
                    $new_type = 'configd_restart_haproxy';
                    break;
                case 'restart_nginx':
                    $new_type = 'configd_restart_nginx';
                    break;
                case 'upload_highwinds':
                    $new_type = 'configd_upload_highwinds';
                    break;
                case 'upload_sftp':
                    $new_type = 'configd_upload_sftp';
                    break;
            }
            $action->type = $new_type;
        }
    }
}
