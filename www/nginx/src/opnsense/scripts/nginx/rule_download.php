#!/usr/local/bin/php
<?php
/**
 *    Copyright (C) 2018 Fabian Franz
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

function download_rules()
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://raw.githubusercontent.com/nbs-system/naxsi/master/naxsi_config/naxsi_core.rules',
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_VERBOSE => 0,
        CURLOPT_MAXREDIRS => 1,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array(
            "User-Agent: OPNsense Firewall"
        )
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    if ($info['http_code'] != 200 || $err) {
        syslog(LOG_ERR, 'Cannot download NAXSI core rules');
        syslog(LOG_ERR, json_encode($info));
        throw new \Exception();
    }
    return $response;
}

function prepare_values($row) {
  $row['match_zone'] = explode('|', $row['match_zone']);
  return $row;
}

function parse_rules($data) {
  $parsed = [];
  $tmp = null;
  $description = array('rule', 'match_type', 'message', 'match', 'match_zone', 'variable', 'value', 'id');

  foreach($data as $line) {
    $line = trim($line);
    $matches = [];
    if (preg_match('/## (.*) ##/', $line, $matches, PREG_UNMATCHED_AS_NULL)) {
      if (isset($tmp)) {
        if (empty($parsed[$tmp])) {
          unset($parsed[$tmp]);
        }
      }
      $tmp = trim($matches[1]);
      $parsed[$tmp] = [];
    } elseif (preg_match('/\S+ "(str|rx):([^\"]+)" "msg:([^\\"]*)" "mz:([^\"]*)" "s:([^\"]*):(\d+)" id:(\d+);/', $line, $matches, PREG_UNMATCHED_AS_NULL)) {
      $parsed[$tmp][] = prepare_values(array_combine($description, $matches));
    }

  }
  return $parsed;
}


echo json_encode(parse_rules(file('naxsi_core.rules')));
#echo json_encode(parse_rules(explode("\n",download_rules())));

