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

$log_file = '/var/log/nginx/csp_violations.log';
$max_file_size = 1024 * 1024 * 30; // 30 MiB
$max_single_record_size = 1024 * 20; // 20 KiB

// make sure we don't have any formatting issues here
if (stristr($_SERVER['CONTENT_TYPE'], 'csp-report') === false) {
    http_response_code(400);
    echo "This endpoint expects JSON data. Please send data using a json mime time (for example application/json)";
    exit(0);
}

if ($json_data = json_decode(file_get_contents('php://input'), true)) {
    http_response_code(204);
  // inject some data for a log viewer to get a relation with the server entry
    $json_data['server_time'] = time();
    $json_data['server_uuid'] = $_SERVER['SERVER-UUID'];
    $json_data = json_encode($json_data);
    if (strlen($json_data) > $max_single_record_size) {
        echo "The payload is too large";
        http_response_code(413);
        exit(0);
    }
    if (file_exists($log_file)) {
        if ((filesize($log_file) + strlen($json_data)) > $max_file_size) {
            // silently drop the data
            http_response_code(200);
            exit(0);
        }
    }
    file_put_contents($log_file, $json_data . PHP_EOL, FILE_APPEND | LOCK_EX);
} else {
    http_response_code(400);
    echo "Your request data cannot be decoded. Please send compliant JSON data.";
    exit(0);
}
