#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2018-2020 Fabian Franz
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
use OPNsense\Nginx\ErrorLogParser;
use OPNsense\Nginx\AccessLogParser;
use OPNsense\Nginx\StreamAccessLogParser;

$log_prefix = '/var/log/nginx/';
$log_suffix = '.log';

if ($_SERVER['argc'] < 6) {
    die('{"error": "Incorrect amount of parameters given"}');
}

// first parameter: error|access
$mode = $_SERVER['argv'][1];
// second parameter: uuid of server
$server = $_SERVER['argv'][2];
// third parameter: file number
$file_no = (strlen($_SERVER['argv'][3]) > 0) ? max(intval($_SERVER['argv'][3]), -1) : -1;
// third parameter: current page
$page = max(intval($_SERVER['argv'][4]), 0);
// fourth parameter: lines per page
$per_page = max(intval($_SERVER['argv'][5]), 0);
// fifth parameter: filter query
$query = json_decode($_SERVER['argv'][6], true);
$nginx = new Nginx();

if (!is_array($query)) {
    $query = array();
}

if ($file_no >= 0) {
    $log_suffix .= ".$file_no.gz";
}

$result = [];
// special case: the global error log
if ($server == 'global') {
    $logparser = new ErrorLogParser($log_prefix . 'error' . $log_suffix, $page, $per_page, $query);
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
                $log_file_name = $log_prefix . basename($server_names) . '.' . $mode . $log_suffix;
                // this entry has no log file, ignore it
                if (!file_exists($log_file_name)) {
                    break;
                }
                $logparser = null;

                if ($mode == 'error') {
                    $logparser = new ErrorLogParser($log_file_name, $page, $per_page, $query);
                } elseif ($mode == 'access') {
                    $logparser = new AccessLogParser($log_file_name, $page, $per_page, $query);
                }
            }
            else {
                die('{"error": "UUID not found"}');
            }
            break;
        case 'streamerror':
        case 'streamaccess':
            if ($data = $nginx->getNodeByReference('stream_server.' . $server)) {
                $mode = str_replace('stream', '', $mode);
                $log_file_name = $log_prefix . 'stream_' . $server . '.' . $mode . $log_suffix;
                // this entry has no log file, ignore it
                if (!file_exists($log_file_name)) {
                    die('{"error": "file not found"}');
                }
                $logparser = null;

                if ($mode == 'error') {
                    $logparser = new ErrorLogParser($log_file_name, $page, $per_page, $query);
                } elseif ($mode == 'access') {
                    $logparser = new StreamAccessLogParser($log_file_name, $page, $per_page, $query);
                }
            } else {
                die('{"error": "UUID not found"}');
            }
            break;
        default:
            die('{"error": "action (' . $mode . ') not found"}');
    }
}


// we cannot parse the file - something went wrong
if ($logparser === null) {
    $result['error'] = 'cannot retrieve requested logs';
}
else {
    $result['lines'] = $logparser->get_result();
    if (count($result['lines']) > 0) {
        $result['pages'] = $logparser->page_count;
        $result['total'] = $logparser->total_lines;
        $result['found'] = $logparser->query_lines;
        $result['returned'] = count($result['lines']);
        $result['query'] = json_encode($query);
    }
    else {
        $result['error'] = 'no lines found';
    }
}

echo json_encode($result);
