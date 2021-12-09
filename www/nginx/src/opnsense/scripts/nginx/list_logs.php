#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2020 Manuel Faux
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

$log_prefix = '/var/log/nginx/';
$log_suffix = '.log';

function list_logfiles($prefix) {
    global $log_prefix;
    $filename = $log_prefix . $prefix;

    $result = [];
    $files = glob("$filename*", GLOB_NOSORT);
    foreach ($files as $file) {
        // Extract number of logrotate (e.g. error.log.4.gz) and set -1 for currently active file
        $number = (strlen($file) > strlen($filename)) ? substr($file, strlen($filename) + 1, -3) : -1;
        $result[$number] = array(
            'filename' => substr($file, strlen($log_prefix)),
            'date' => ($number >= 0) ? date('d/M/Y', filemtime($file) - 3600) : 'current',
            'number' => $number
        );
    }

    ksort($result, SORT_NUMERIC);
    $result = array_values($result);

    return $result;
}

if ($_SERVER['argc'] < 3) {
    die('{"error": "Incorrect amount of parameters given"}');
}

// first parameter: error|access
$mode = $_SERVER['argv'][1];
// second parameter: uuid of server
$server = $_SERVER['argv'][2];
$nginx = new Nginx();

$result = [];
// special case: the global error log
if ($server == 'global') {
    $result = list_logfiles('error.log');
}
else {
    switch ($mode) {
        case 'error':
        case 'access':
            if ($data = $nginx->getNodeByReference('http_server.' . $server)) {
                $server_names = (string)$data->servername;
                if (empty($server_names)) {
                    die('{"error": "The server entry has no server name"}');
                }

                $log_file_name = basename($server_names) . '.' . $mode . $log_suffix;
                $result = list_logfiles($log_file_name);
            }
            else {
                die('{"error": "UUID not found"}');
            }
            break;
        case 'streamerror':
        case 'streamaccess':
            if ($data = $nginx->getNodeByReference('stream_server.' . $server)) {
                $mode = str_replace('stream', '', $mode);
                $log_file_name = 'stream_' . $server . '.' . $mode . $log_suffix;
                $result = list_logfiles($log_file_name);
            } else {
                die('{"error": "UUID not found"}');
            }
            break;
        default:
            die('{"error": "action (' . $mode . ') not found"}');
    }
}

echo json_encode($result);
