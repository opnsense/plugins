#!/usr/local/bin/php
<?php
/*
 * Copyright (C) 2018 Fabian Franz
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
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

require_once 'config.inc';
use OPNsense\Nginx\Nginx;
use OPNsense\Nginx\ErrorLogParser;
use OPNsense\Nginx\AccessLogParser;
$log_prefix = '/var/log/nginx/';
$log_suffix = '.log';


if ($_SERVER['argc'] != 3) die('{"error": "Incorrect amount of parameters given"}');

// first parameter: error|access
$mode = $_SERVER['argv'][1];
// second parameter: uuid of server
$server = $_SERVER['argv'][2];
$nginx = new Nginx();

if ($data = $nginx->getNodeByReference('http_server.'. $server)) {
    $server_names = (string)$data->servername;
    if (empty($server_names)) {
        die('{"error": "The server entry has no server name"}');
    }
    $lines = [];
    foreach (explode(',', $server_names) as $server_name) {
        $log_file_name = $log_prefix . basename($server_name) . '.' . $mode . $log_suffix;
        // this entry has no log file, ignore it
        if (!file_exists($log_file_name)) continue;
        $logparser = null;

        if ($mode == 'error') {
            $logparser = new ErrorLogParser($log_file_name);
        }
        elseif ($mode == 'access') {
            $logparser = new AccessLogParser($log_file_name);
        }
        // we cannot parse the file - something went wrong
        if ($logparser == null) continue;
        $lines = array_merge($lines, $logparser->get_result());
    }
    if (empty($lines)) {
        $lines['error'] = 'no lines found';
    }
    echo json_encode($lines);
}
else {
    die('{"error": "UUID not found"}');
}
