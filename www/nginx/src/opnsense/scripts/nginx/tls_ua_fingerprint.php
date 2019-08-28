#!/usr/local/bin/php
<?php
/*
 * Copyright (C) 2018 Fabian Franz
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

function parse_line($line)
{
    $tmp = explode('"', trim($line));
    return array('ua' => $tmp[1], 'ciphers' => $tmp[3], 'curves' => $tmp[5] == '-' ? '' : $tmp[5], 'count' => 1);
}
function filter_ua($key)
{
    return $key != 'ua';
}


// parse all finger prints and count them
$fingerprints = array();
$handle = @fopen('/var/log/nginx/tls_handshake.log', 'r');
if ($handle) {
    while (($buffer = fgets($handle)) !== false) {
        $md5line = md5($buffer);
        if (array_key_exists($md5line, $fingerprints)) {
            $fingerprints[$md5line]['count']++;
        } else {
            $parsed_line = parse_line($buffer);
            if ($parsed_line['ciphers'] != '-') {
                $fingerprints[$md5line] = $parsed_line;
            }
        }
    }
    fclose($handle);
}
unset($handle);

// group by user agent
$fingerprints2 = array();
foreach ($fingerprints as $fingerprint) {
    $user_agent = $fingerprint['ua'];
    if (!is_array($fingerprints2[$user_agent])) {
        $fingerprints2[$user_agent] = array();
    }
    $hashkey = md5($fingerprint['ciphers'] . $fingerprint['curves']);
    $fingerprints2[$user_agent][$hashkey] = array_filter($fingerprint, 'filter_ua', ARRAY_FILTER_USE_KEY);
}

// free some memory so we have more to use
unset($fingerprints);
unset($hashkey);
unset($md5line);

// remove the hash key
$fingerprints = array();
foreach ($fingerprints2 as $ua => $fingerprint_data) {
    $fingerprints[$ua] = array_values($fingerprint_data);
}
unset($fingerprints2);


echo json_encode($fingerprints);
