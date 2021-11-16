#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2016-2021 Frank Wall
 * Copyright (C) 2015 Deciso B.V.
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

// Use legacy code to export certificates to the filesystem.
require_once("config.inc");
require_once("certs.inc");
require_once("legacy_bindings.inc");

use OPNsense\Core\Config;

$export_path = '/tmp/haproxy/lua/';

// traverse HAProxy Lua scripts
$configObj = Config::getInstance()->object();
if (isset($configObj->OPNsense->HAProxy->luas)) {
    foreach ($configObj->OPNsense->HAProxy->luas->children() as $lua) {
        if (!isset($lua->enabled)) {
            continue;
        }
        $lua_name = (string)$lua->name;
        $lua_id = (string)$lua->id;
        $lua_filename_scheme = (string)$lua->filename_scheme;
        if ($lua_filename_scheme != '' and $lua_filename_scheme === 'name') {
            $_name_alnum = preg_replace("/[^A-Za-z0-9]/", '', $lua_name);
            $lua_filename = $export_path . $_name_alnum . '.lua';
        } else {
            $lua_filename = $export_path . $lua_id . '.lua';
        }
        $lua_content = htmlspecialchars_decode(str_replace("\r", "", (string)$lua->content));
        file_put_contents($lua_filename, $lua_content);
        chmod($lua_filename, 0600);
        chown($lua_filename, 'www');
        echo "lua script exported to " . $lua_filename . "\n";
    }
}
