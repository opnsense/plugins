<?php

/*
 * Copyright (C) 2021 Markus Peter <mpeter@one-it.de>
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

namespace OPNsense\Nginx\Migrations;

use OPNsense\Base\BaseModelMigration;

class M1_24_0 extends BaseModelMigration
{
    public function run($model)
    {
        foreach ($model->getNodeByReference('http_server')->iterateItems() as $http_server) {
            if ($http_server->listen_http_port != '') {
                $http_server->listen_http_address = $http_server->listen_http_port . ',[::]:' . $http_server->listen_http_port;
                $http_server->listen_http_port = null;
            }
            if ($http_server->listen_https_port != '') {
                $http_server->listen_https_address = $http_server->listen_https_port . ',[::]:' . $http_server->listen_https_port;
                $http_server->listen_https_port = null;
            }
        }
        foreach ($model->getNodeByReference('stream_server')->iterateItems() as $server) {
            if ($server->listen_port != '') {
                $server->listen_address = $server->listen_port . ',[::]:' . $server->listen_port;
                $server->listen_port = null;
            }
        }
    }
}
