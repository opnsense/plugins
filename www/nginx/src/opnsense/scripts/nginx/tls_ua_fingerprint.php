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
$tls_logfile = '/var/log/nginx/tls_handshake.log.work';
$database_name = '/var/log/nginx/handshakes.json';

function parse_line($line)
{
    // ignore GREASE cipher suite values when compiling a browser fingerprint (see rfc8701)
    $GREASE = array("0x0a0a", "0x1a1a", "0x2a2a", "0x3a3a", "0x4a4a", "0x5a5a", "0x6a6a", "0x7a7a", "0x8a8a", "0x9a9a", "0xaaaa", "0xbaba", "0xcaca", "0xdada", "0xeaea", "0xfafa");
    // ignore SCSV cipher suite values when compiling a browser fingerprint (see rfc5746 and rfc7507)
    $SCSV = array("TLS_EMPTY_RENEGOTIATION_INFO_SCSV", "TLS_FALLBACK_SCSV");
    $tmp = explode('"', trim($line));
    $fp = array(
        'ua' => $tmp[1],
        'ciphers' => $tmp[3],
        'curves' => $tmp[5] == '-' ? '' : $tmp[5],
        'count' => 1
    );
    // exclude GREASE and SCSV suits from fingerprint
    $fp_ciphers = explode(':', $fp['ciphers']);
    $fp_ciphers = array_diff($fp_ciphers, $GREASE, $SCSV);
    $fp['ciphers'] = implode(':', $fp_ciphers);
    $fp_curves = explode(':', $fp['curves']);
    $fp_curves = array_diff($fp_curves, $GREASE);
    $fp['curves'] = implode(':', $fp_curves);
    return $fp;
}
function filter_ua($key)
{
    return $key != 'ua';
}


if (!file_exists($tls_logfile)) {
    echo "logfile $tls_logfile does not exist\n";
    exit(0);
}

$fingerprints_old = @json_decode(@file_get_contents($database_name), true);
if (!is_array($fingerprints_old)) {
    $fingerprints_old = array();
}


// parse all finger prints and count them
$fingerprints = array();
$handle = @fopen($tls_logfile, 'r');
if ($handle) {
    while (($buffer = fgets($handle)) !== false) {
        $parsed_line = parse_line($buffer);
        if ($parsed_line['ciphers'] != '-') {
            $md5fp = md5($parsed_line['ua'] . $parsed_line['ciphers'] . $parsed_line['curves']);
            if (array_key_exists($md5fp, $fingerprints)) {
                $fingerprints[$md5fp]['count']++;
            } else {
                $fingerprints[$md5fp] = $parsed_line;
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
    if (isset($fingerprints2[$user_agent]) && !is_array($fingerprints2[$user_agent])) {
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


foreach ($fingerprints_old as $ua_old => $fingerprint_data_old) {
    if (isset($fingerprints[$ua_old])) {
        foreach ($fingerprint_data_old as &$fpdo) {
            $changed = false;
            foreach ($fingerprints[$ua_old] as &$fingerprint_new) {
                if ($fpdo['ciphers'] == $fingerprint_new['ciphers'] && $fpdo['curves'] == $fingerprint_new['curves']) {
                    $changed = true;
                    $fingerprint_new['count'] = $fingerprint_new['count'] + $fpdo['count'];
                    break;
                }
            }
            if (!$changed) {
                $fingerprints[$ua_old][] = $fpdo;
            }
        }
    } else {
        // the new log does not have it, apply the old one
        $fingerprints[$ua_old] = $fingerprint_data_old;
    }
}

file_put_contents($database_name, json_encode($fingerprints));
unlink($tls_logfile);
