<?php

/**
 *    Copyright (C) 2023 Frank Wall
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
use OPNsense\Core\Config;
use OPNsense\Cron\Cron;

class M4_1_0 extends BaseModelMigration
{
    public function run($model)
    {
        // Get old OCSP config item and map to new value
        $old_ocsp = (string)$model->general->storeOcsp;
        $model->general->tuning->ocspUpdateEnabled = $old_ocsp;

        // Remove obsolete OCSP cron job
        if ((string)$model->maintenance->cronjobs->updateOcspCron != "") {
            $cron_uuid = (string)$model->maintenance->cronjobs->updateOcspCron;
            $model->maintenance->cronjobs->updateOcspCron = "";

            // Delete the cronjob item
            $mdlCron = new Cron();
            if ($mdlCron->jobs->job->del($cron_uuid)) {
                $mdlCron->serializeToConfig();
                $model->serializeToConfig($validateFullModel = false, $disable_validation = true);
                Config::getInstance()->save();
            }
        }
    }
}
