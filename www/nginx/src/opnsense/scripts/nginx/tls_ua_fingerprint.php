#!/usr/local/bin/php
<?php

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
    if (!in_array($fingerprint['ua'], $fingerprints2)) {
        $fingerprints2[$fingerprint['ua']] = array();
    }
    $hashkey = md5($fingerprint['ciphers'] . $fingerprint['curves']);
    $fingerprints2[$fingerprint['ua']][$hashkey] = array_filter($fingerprint, 'filter_ua', ARRAY_FILTER_USE_KEY);
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
