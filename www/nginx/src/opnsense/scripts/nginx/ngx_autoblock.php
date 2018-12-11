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

require_once('config.inc');
require_once('IPv6.inc');

use OPNsense\Firewall\Alias;
use OPNsense\Nginx\AccessLogParser;
use OPNsense\Core\Config;
use OPNsense\Nginx\Nginx;

function nginx_print_error($msg)
{
    echo json_encode(
        array('status' => 'error', 'message' => $msg)
    );
}
function exec_hidden($command): void
{
    $descriptorspec = array(
        1 => array('file', "/dev/null", 'w'),
        2 => array('file', "/dev/null", "w")
    );

    $process = proc_open($command, $descriptorspec, $pipes);

    if (is_resource($process)) {
        proc_close($process);
    }
}
function add_to_blocklist($tablename, $ip)
{
    $escaped = escapeshellarg($ip);
    exec_hidden("/sbin/pfctl -t ${tablename} -T add ${escaped}");
}


function reopen_logs()
{
    exec_hidden('/usr/local/sbin/nginx -s reopen');
}

$permanent_ban_file = '/var/log/nginx/permanentban.access.log';
$permanent_ban_file_work = $permanent_ban_file . '.work';
$autoblock_alias_name = 'nginx_autoblock';


if (!file_exists($permanent_ban_file)) {
    nginx_print_error('No Log exists - nothing to do');
    // let create it
    reopen_logs();
    exit(0);
}

// move the file, and inform nginx that we deleted the file
rename($permanent_ban_file, $permanent_ban_file_work);
reopen_logs();

$log_parser = new AccessLogParser($permanent_ban_file_work);

$log_lines = $log_parser->get_result();

$model = new Alias();

$blacklist_element = null;
foreach ($model->aliases->alias->iterateItems() as $alias) {
    if ((string)$alias->name == $autoblock_alias_name) {
        if ((string)$alias->type != 'external') {
            nginx_print_error('alias is misconfigured - exiting');
            exit(0);
        } else {
            $blacklist_element = $alias;
            break;
        }
    }
}

// does not exist yet, create it
if ($blacklist_element == null) {
    $blacklist_element = $model->aliases->alias->Add();
    $blacklist_element->name = $autoblock_alias_name;
    $blacklist_element->type = "external";
    $model->serializeToConfig();
}

$model = new Nginx();
$alias_ips = [];
foreach ($model->ban->iterateItems() as $entry) {
    $alias_ips[] = (string)$entry->ip;
}

$new_ips = array_unique(
    array_map(function ($row) {
        if (stripos($row->remote_ip, '.') !== false) {
            return $row->remote_ip;
        }
        // in case of IPv6, we have to use the network address instead
        // danger of DoS because the attacker should have at least 2 ** 64 IPs
        return Net_IPv6::getNetmask($row->remote_ip, 64) . '/64';
    }, $log_lines)
);

$change_required = false;

foreach (array_diff($new_ips, $alias_ips) as $new_ip) {
    $entry = $model->ban->Add();
    $entry->ip = $new_ip;
    $entry->time = time();
    $change_required = true;
}

if ($change_required) {
    $val_result = $model->performValidation(false);
    if (count($val_result) !== 0) {
        print_r($val_result);
        exit(1);
    }

    $model->serializeToConfig();
    Config::getInstance()->save();
}
echo '{"status":"saved"}';

// all ips are used because the others may not be set for some reason
foreach ($model->ban->iterateItems() as $entry) {
    add_to_blocklist($autoblock_alias_name, (string)$entry->ip);
}

@unlink($permanent_ban_file_work);
