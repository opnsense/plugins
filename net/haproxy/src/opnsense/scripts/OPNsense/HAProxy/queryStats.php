#!/usr/local/bin/php
<?php

/**
 *    Copyright (C) 2016 Frank Wall
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

$haproxy_socket = '/var/run/haproxy.socket';

function socketCmd($command)
{
    global $haproxy_socket;
    $data = array();
    if (!file_exists($haproxy_socket)) {
        exit("HAProxy socket does not exist, service may be stopped");
    } else {
        $socket = @stream_socket_client("unix://$haproxy_socket", $errorNumber, $errorMessage);
        if (!$socket) {
            exit("Unable to open socket: $errorMessage");
        } else {
            fwrite($socket, "$command\n");
            while (!feof($socket)) {
                // XXX: The #-prefix is annoying.
                $data[] = trim(preg_replace('/#/', '', fgets($socket)));
            }
            fclose($socket);
        }
    }
    return $data;
}

function showInfo()
{
    $data = array();
    $show_info = socketCmd('show info');
    foreach ($show_info as $line) {
        if (empty(trim($line))) {
            continue;
        }
        $values = explode(':', $line);
        $data[trim($values[0])] = trim($values[1]);
    }
    return $data;
}

function showTable()
{
    $data = array();
    $show_table = socketCmd('show table');
    foreach ($show_table as $line) {
        if (empty(trim($line))) {
            continue;
        }
        $line = preg_replace('/#/', '', $line);
        $values = explode(',', $line);
        $items = array();
        foreach ($values as $value) {
            $item = explode(':', trim($value));
            $items[$item[0]] = trim($item[1]);
        }
        $data[] = $items;
    }
    return $data;
}

function showStat()
{
    $show_stat = socketCmd('show stat');
    // output is a list of CSV
    $stat_csv = array_map('str_getcsv', $show_stat);
    array_walk($stat_csv, function (&$a) use ($stat_csv) {
        // XXX: Ignore empty/incomplete entries.
        if (count($a) > 1) {
            $a = array_combine($stat_csv[0], $a);
        }
    });
    array_shift($stat_csv); # remove column header
    foreach ($stat_csv as &$value) {
        // Add unique identifier.
        if (! empty($value['pxname']) and $value['pxname'] != null) {
            $value['id'] = $value['pxname'] . '/' . $value['svname'];
        }
    }
    return $stat_csv;
}

switch ($argv[1]) {
    case 'info':
        $result = showInfo();
        echo json_encode($result);
        break;
    case 'table':
        $result = showTable();
        echo json_encode($result);
        break;
    case 'stat':
        $result = showStat();
        echo json_encode($result);
        break;
    default:
        echo "not a valid argument\n";
}
