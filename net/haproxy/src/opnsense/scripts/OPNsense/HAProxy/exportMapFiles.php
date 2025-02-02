#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2016-2018 Frank Wall
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

use OPNsense\Core\Config;

$export_path = '/tmp/haproxy/mapfiles/';

// traverse HAProxy map files
$configObj = Config::getInstance()->object();
if (isset($configObj->OPNsense->HAProxy->mapfiles)) {
    foreach ($configObj->OPNsense->HAProxy->mapfiles->children() as $mapfile) {
        $mf_name = (string)$mapfile->name;
        $mf_id = (string)$mapfile->id;
        if ($mf_id != "") {
            $mf_content = htmlspecialchars_decode(str_replace("\r", "", (string)$mapfile->content));
            $mf_filename = $export_path . $mf_id . ".txt";
            file_put_contents($mf_filename, $mf_content);
            chmod($mf_filename, 0600);
            echo "map file exported to " . $mf_filename . "\n";
        }
    }
}
