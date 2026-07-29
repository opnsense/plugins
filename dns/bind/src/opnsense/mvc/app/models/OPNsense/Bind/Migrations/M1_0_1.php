<?php

/*
 * Copyright (C) 2026 Bryan Wiegand <inbox@kw-ventures.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the
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
use OPNsense\Bind\Forwarder;
use OPNsense\Core\Config;

class M1_0_1 extends BaseModelMigration
{
    /**
     * Migrate the legacy General model forwarders CSV into this model's DNS
     * forwarder grid.
     * @param $model
     */
    public function run($model)
    {
        if (!($model instanceof Forwarder)) {
            return;
        }

        $config = Config::getInstance()->object();
        if (empty($config->OPNsense->bind->general->forwarders)) {
            return;
        }

        $legacy = trim((string)$config->OPNsense->bind->general->forwarders);
        if ($legacy === '') {
            return;
        }

        if (count(iterator_to_array($model->forwarders->dns->iterateItems())) > 0) {
            return;
        }

        foreach (explode(',', $legacy) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            $ip = $token;
            $port = '53';

            if (filter_var($token, FILTER_VALIDATE_IP) === false && strpos($token, ':') !== false) {
                [$candidateIp, $candidatePort] = explode(':', $token, 2);
                if (filter_var($candidateIp, FILTER_VALIDATE_IP) !== false &&
                    ctype_digit($candidatePort) && $candidatePort >= 1 && $candidatePort <= 65535
                ) {
                    $ip = $candidateIp;
                    $port = $candidatePort;
                } else {
                    syslog(LOG_WARNING, sprintf('BIND migration: no valid port found in "%s", defaulting to 53.', $token));
                }
            }

            $forwarder = $model->forwarders->dns->add();
            $forwarder->ip = $ip;
            $forwarder->port = $port;
        }

        $config->OPNsense->bind->general->forwarders = '';
    }
}
