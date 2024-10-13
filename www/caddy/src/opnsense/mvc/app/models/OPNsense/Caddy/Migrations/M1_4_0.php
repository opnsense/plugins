<?php

/*
 *    Copyright (C) 2024 Cedrik Pischem
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
 */

namespace OPNsense\Caddy\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

/*
 * Empty HandlePath start at high decreasing sequence
 * to generate them last in the Caddyfile, non-empty HandlePath are the opposite.
 * If the sequence becomes empty, the Caddyfile template has a default to 0.
 */

// @codingStandardsIgnoreStart
class M1_4_0 extends BaseModelMigration
// @codingStandardsIgnoreEnd
{
    public function run($model)
    {
        $config = Config::getInstance()->object();

        if (!empty($config->Pischem->caddy->reverseproxy)) {
            static $emptySequence = 99999;
            static $nonEmptySequence = 1;

            foreach ($config->Pischem->caddy->reverseproxy->handle as $configNode) {
                $uuid = (string)$configNode->attributes()->uuid;
                $modelNode = $model->getNodeByReference('reverseproxy.handle.' . $uuid);

                if ($modelNode === null) {
                    continue;
                }

                if (empty((string)$configNode->HandlePath)) {
                    if ($emptySequence < 50000) {
                        $modelNode->Sequence = '';
                    } else {
                        $modelNode->Sequence = $emptySequence--;
                    }
                } else {
                    if ($nonEmptySequence >= 50000) {
                        $modelNode->Sequence = '';
                    } else {
                        $modelNode->Sequence = $nonEmptySequence++;
                    }
                }
            }
        }
    }

    // Model is saved by 'run_migrations.php'
}
