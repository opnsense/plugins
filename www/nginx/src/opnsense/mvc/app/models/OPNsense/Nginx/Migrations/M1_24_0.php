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
use OPNsense\Core\Config;

class M1_24_0 extends BaseModelMigration
{
    /**
     * Listen ports to listen addresses movements
     * @param Nginx $model
     */
    public function run($model)
    {
        $cfgObj = Config::getInstance()->object();
        $ports = array();
        foreach ($cfgObj->OPNsense->Nginx->http_server ?? [] as $cfg_http_server) {
            $uuid = (string)$cfg_http_server->attributes()['uuid'];
            $ports['http_port'] = (isset($cfg_http_server->listen_http_port) && $cfg_http_server->listen_http_port != '') ? $cfg_http_server->listen_http_port : null;
            $ports['https_port'] = (isset($cfg_http_server->listen_https_port) && $cfg_http_server->listen_https_port != '') ? $cfg_http_server->listen_https_port : null;
            $http_server = $model->getNodeByReference('http_server.' . $uuid);
            $http_server->listen_http_address = (isset($ports['http_port'])) ? $ports['http_port'] . ',[::]:' . $ports['http_port'] : null;
            $http_server->listen_https_address = (isset($ports['https_port'])) ? $ports['https_port'] . ',[::]:' . $ports['https_port'] : null;
        }
        foreach ($cfgObj->OPNsense->Nginx->stream_server ?? [] as $cfg_stream_server) {
            $uuid = (string)$cfg_stream_server->attributes()['uuid'];
            $port = (isset($cfg_stream_server->listen_port) && $cfg_stream_server->listen_port != '') ? $cfg_stream_server->listen_port : null;
            $server = $model->getNodeByReference('stream_server.' . $uuid);
            $server->listen_address = (isset($port)) ? $port . ',[::]:' . $port : null;
        }
        // run default migration actions
        parent::run($model);
    }
}
