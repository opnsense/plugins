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
require_once('util.inc');

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

function modify_blocklist($tablename, array $allIps, $operation = "add"): void
{
    if (empty($allIps) || !in_array($operation, ["add", "delete"]))
        return;

    $tablename = escapeshellarg($tablename);
    $operation = escapeshellarg($operation);

    $longestIp = array_reduce($allIps, function ($length, $ip) {
        return max($length, strlen(escapeshellarg($ip)));
    }, 0);

    $chunkSize = floor(4096 / ($longestIp + 1));
    $chunkSize = min(128, max(4, $chunkSize));

    foreach (array_chunk($allIps, $chunkSize) as $ips) {
        $escapedIps = join(" ", array_map("escapeshellarg", $ips));

        exec_hidden("/sbin/pfctl -t ${tablename} -T ${operation} ${escapedIps}");
    }
}

function read_all_from_blocklist($tablename)
{
    $tablename = escapeshellarg($tablename);

    $descriptorspec = [
        1 => ['pipe', 'w'],
        2 => ['file', "/dev/null", "w"],
    ];

    $process = proc_open("/sbin/pfctl -t ${tablename} -T show", $descriptorspec, $pipes);
    if (is_resource($process)) {
        $ips = [];
        while ($ip = fgets($pipes[1], 96))
            $ips[] = strtolower(trim($ip));

        fclose($pipes[1]);
        proc_close($process);

        return $ips;
    } else {
        return false;
    }
}

function get_files_lastmodified(array $files): array
{
    // Maps [file => filemtime]
    // File times of special files:
    // - Non existing => random mtime
    // - No content => -1
    $times = [];
    foreach ($files as $file) {
        $mtime = @filemtime($file) ?: rand();
        $times[$file] = @filesize($file) === 0 ? -1 : $mtime;
    }
    return $times;
}

function reopen_logs()
{
    exec_hidden('/usr/local/sbin/nginx -s reopen');
}

const STATE_FILE = '/tmp/ngx_autoblock.state.json';
const CONFIG_FILE = '/conf/config.xml';

const PERMANENT_BAN_FILE = '/var/log/nginx/permanentban.access.log';
const PERMANENT_BAN_FILE_WORK = PERMANENT_BAN_FILE . '.work';

const TLS_HANDSHAKE_FILE = '/var/log/nginx/tls_handshake.log';
const TLS_HANDSHAKE_FILE_WORK = TLS_HANDSHAKE_FILE . '.work';
const TLS_HANDSHAKE_PROCESSING_TASK = '/usr/local/opnsense/scripts/nginx/tls_ua_fingerprint.php';

const AUTOBLOCK_ALIAS_NAME = 'nginx_autoblock';

const CRON_RUN_TEN_MINUTES = 10;
$is_ten_minutes = intval(date('i')) % CRON_RUN_TEN_MINUTES == 0;

// Move log files and inform Nginx that we deleted them
function create_work_files($include_tls_handshake)
{
    $mapping = [PERMANENT_BAN_FILE => PERMANENT_BAN_FILE_WORK];
    if ($include_tls_handshake) {
        $mapping[TLS_HANDSHAKE_FILE] = TLS_HANDSHAKE_FILE_WORK;
    }

    $existing_sources = array_filter(array_keys($mapping), "file_exists");
    $work_files = [];

    if (count($existing_sources) == count($mapping)) {
        foreach ($mapping as $source => $target) {
            // Check if we already processing $target in another process and skip it if not stale
            if (file_exists($target)) {
                if (time() - (@filemtime($target) ?: 0) > (5 * 60))
                    @unlink($target);
                else
                    continue;
            }

            // Try to create work and log on failure
            if (@rename($source, $target)) {
                @touch($target);
                $work_files[] = $target;
            } else {
                log_error("Failed renaming '$source' to '$target'. Skipping source for next run.");
            }
        }
    } else {
        //Concurrent invocation. Can be silently ignored since no work files are collected.
        //log_error("Skipping processing. Missing: " . join(", ", array_diff(array_keys($mapping), $existing_sources)));
    }

    reopen_logs();
    register_shutdown_function("cleanup_work_files", $work_files);

    return $work_files;
}

function cleanup_work_files($work_files)
{
    foreach ($work_files as $file)
        @unlink($file);
}

// Checking if our sources are modified and create work files as needed (do nothing if sources are unchanged)
$work_files = (function () use ($is_ten_minutes) {
    $sources = get_files_lastmodified([CONFIG_FILE, PERMANENT_BAN_FILE]);

    $state = @json_decode(@file_get_contents(STATE_FILE), true);
    $changed = empty($state)
        || !isset($state["sources"])
        || $state["sources"] != $sources;

    if ($changed || $is_ten_minutes) {
        // Rename sources to ".work" and tell nginx to reopen logs.
        // Triggering TLS-handshake processor every 10 minutes.
        $work_files = create_work_files($is_ten_minutes);

        // Store state
        if (!empty($work_files)) {
            if (!is_array($state))
                $state = [];
            $state["sources"] = get_files_lastmodified(array_keys($sources));
            @file_put_contents(STATE_FILE, json_encode($state));
        }

        return $work_files;
    } else {
        // Sources are not modified, nothing to do when not in "$is_ten_minutes".
        exit(0);
    }
})();

// Triggering TLS-handshake processor when corresponding work file exists.
if (in_array(TLS_HANDSHAKE_FILE_WORK, $work_files)) {
    mwexec(TLS_HANDSHAKE_PROCESSING_TASK);
}

// Abort if permanent ban file is missing
if (!in_array(PERMANENT_BAN_FILE_WORK, $work_files)) {
    nginx_print_error('No Log exists - nothing to do');
    exit(0);
}

// Verifing autoblock fw-alias and adding it if missing
(function () {
    $model = new Alias();

    $blacklist_element = null;
    foreach ($model->aliases->alias->iterateItems() as $alias) {
        if ((string)$alias->name == AUTOBLOCK_ALIAS_NAME) {
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
        $blacklist_element->name = AUTOBLOCK_ALIAS_NAME;
        $blacklist_element->type = "external";
        $model->serializeToConfig();
    }
})();

// Getting new banned IPs list
$banned_ips = (function () {
    // Reading stored banned IPs from config
    $model = new Nginx();
    $alias_ips = [];
    foreach ($model->ban->iterateItems() as $entry) {
        $alias_ips[] = (string)$entry->ip;
    }

    // Collecting all new IPs from ban file not yet in $alias_ips.
    $new_ips = (function () use ($alias_ips) {
        // Read IPs from the log file
        $log_parser = new AccessLogParser(PERMANENT_BAN_FILE_WORK);
        $log_lines = $log_parser->get_result();
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

        // Return only IPs not yet in $alias_ips
        return array_diff($new_ips, $alias_ips);
    })();

    // Transfering new IPs into $alias_ips and store them permanently.
    $new_and_alias_ips = (function () use ($model, $new_ips, $alias_ips) {
        $change_required = false;

        foreach ($new_ips as $new_ip) {
            $alias_ips[] = $new_ip;

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

        return $alias_ips;
    })();

    // Returning banned IPs (= combination of (new_ips + alias_ips))
    return $new_and_alias_ips;
})();

echo '{"status":"saved"}';

// Updating PF table with banned IPs
(function () use ($banned_ips) {
    $ips_to_add = $banned_ips;
    $ips_to_remove = [];

    // Checking which IPs are in the table and apply changes
    $ips_in_table = read_all_from_blocklist(AUTOBLOCK_ALIAS_NAME);
    if (!empty($ips_in_table)) {
        $ips_to_add = array_diff($banned_ips, $ips_in_table);
        $ips_to_remove = array_diff($ips_in_table, $banned_ips);
    }

    modify_blocklist(AUTOBLOCK_ALIAS_NAME, $ips_to_add, "add");
    modify_blocklist(AUTOBLOCK_ALIAS_NAME, $ips_to_remove, "delete");
})();
