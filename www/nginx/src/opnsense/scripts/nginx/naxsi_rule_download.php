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

require_once('config.inc');
use OPNsense\Core\Config;
use OPNsense\Nginx\Nginx;

function download_rules()
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://raw.githubusercontent.com/nbs-system/naxsi/master/naxsi_config/naxsi_core.rules',
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_VERBOSE => 0,
        CURLOPT_MAXREDIRS => 1,
        CURLOPT_TIMEOUT => 10,
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
        exit(1);
    }
    return $response;
}

function prepare_values($row)
{
    $row['match_zone'] = explode('|', $row['match_zone']);
    return $row;
}

function parse_rules($data)
{
    $parsed = [];
    $tmp = null;
    $description = array('rule', 'match_type', 'match', 'message', 'match_zone', 'variable', 'score', 'id');

    foreach ($data as $line) {
        $line = trim($line);
        $matches = [];
        if (preg_match('/## (.*) ##/', $line, $matches)) {
            if (isset($tmp) && empty($parsed[$tmp])) {
                unset($parsed[$tmp]);
            }
            $tmp = trim($matches[1]);
            $parsed[$tmp] = [];
        } elseif (preg_match('/\S+ "(str|rx):([^\"]+)" "msg:([^\\"]*)" "mz:([^\"]*)" "s:([^\"]*):(\d+)" id:(\d+);/', $line, $matches)) {
            $parsed[$tmp][] = prepare_values(array_combine($description, $matches));
        }
    }
    return $parsed;
}

function save_to_model($data)
{
    $model = new Nginx();
    foreach ($data as $group => $rules) {
        // create a new policy
        $policy = $model->custom_policy->Add();
        $policy->name = $group;
        $policy->value = '8';
        $policy->operator = '>=';
        $policy->action = 'BLOCK';
        // create new values for policy
        $rule_list = [];
        $dis_rules = [];
        foreach ($rules as $rule) {
            $rule_mdl = $model->naxsi_rule->Add();
            // exclude commented rules from policy
            if (str_starts_with($rule['rule'], '#')) {
                $dis_rules[] = (string)$rule_mdl->getAttributes()["uuid"];
            }
            $rule_mdl->description = $rule['message'];
            $rule_mdl->message = $rule['message'];
            $rule_mdl->ruletype = 'main';
            $rule_mdl->match_type = 'id';
            $rule_mdl->identifier = $rule['id'];
            $rule_mdl->match_value = $rule['match'];
            $rule_mdl->regex = $rule['match_type'] == 'str' ? '0' : '1';
            // default to 0
            $rule_mdl->args = '0';
            $rule_mdl->headers = '0';
            $rule_mdl->name = '0';
            $rule_mdl->body = '0';
            $rule_mdl->url = '0';
            $rule_mdl->raw_body = '0';
            $rule_mdl->file_extension = '0';
            $rule_mdl->negate = '0';
            $rule_mdl->score = $rule['score'];
            foreach ($rule['match_zone'] as $match_zone) {
                if (stripos($match_zone, ':') === false) {
                    switch ($match_zone) {
                        case 'ARGS':
                            $rule_mdl->args = '1';
                            break;
                        case 'HEADERS':
                            $rule_mdl->headers = '1';
                            break;
                        case 'NAME':
                            $rule_mdl->name = '1';
                            break;
                        case 'BODY':
                            $rule_mdl->body = '1';
                            break;
                        case 'URL':
                            $rule_mdl->url = '1';
                            break;
                        case 'RAW_BODY':
                            $rule_mdl->raw_body = '1';
                            break;
                        case 'FILE_EXT':
                            $rule_mdl->file_extension = '1';
                            break;
                    }
                } else {
                    $kv = explode(':', $match_zone);
                    switch ($kv[0]) {
                        case '$BODY_VAR':
                            $rule_mdl->dollar_body_var = $kv[1];
                            break;
                        case '$ARGS_VAR':
                            $rule_mdl->dollar_args_var = $kv[1];
                            break;
                        case '$HEADERS_VAR':
                            $rule_mdl->dollar_headers_var = $kv[1];
                    }
                }
            }
            $rule_list[] = $rule_mdl->getAttributes()["uuid"];
        }
        $policy->naxsi_rules = implode(',', array_diff($rule_list, $dis_rules));
    }
    $val_result = $model->performValidation(false);
    if (count($val_result) !== 0) {
        print_r($val_result);
        exit(1);
    }

    $model->serializeToConfig();
    Config::getInstance()->save();
}


#$data =  parse_rules(file('./naxsi_core.rules'));
$data = parse_rules(explode("\n", download_rules()));
save_to_model($data);
