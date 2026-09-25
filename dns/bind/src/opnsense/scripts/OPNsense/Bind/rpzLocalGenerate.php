#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 opnsense.org community
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
 *
 * Generates zone files for RPZ zones with type=local from their RpzEntry
 * rows. Runs on every BIND restart/reconfigure (hooked into the [restart]
 * configd action, alongside generate_certs.php).
 *
 * Deliberately does NOT touch type=feed zones (rpz.local, urlhaus.rpz,
 * etc.) — those are downloaded/managed by rpz-update-<name>.sh. This script
 * only ever iterates zones explicitly marked type=local, so it structurally
 * cannot overwrite feed-managed content regardless of what's in config.xml.
 */

require_once("script/load_phalcon.php");

use OPNsense\Core\Config;

const PRIMARY_DIR = "/usr/local/etc/namedb/primary";

// Returns an array of zone-file lines for one entry, or [] to skip it
// (e.g. a redirect/local-a entry missing its required value — better to
// silently omit than emit a broken record).
function rpz_entry_lines($qname, $action, $redirect_target, $ipv4, $ipv6)
{
    switch ((string)$action) {
        case 'local-a':
            $lines = [];
            $ipv4 = trim((string)$ipv4);
            if ($ipv4 !== '') {
                $lines[] = sprintf('%s A %s', $qname, $ipv4);
            }
            $ipv6 = trim((string)$ipv6);
            if ($ipv6 !== '') {
                $lines[] = sprintf('%s AAAA %s', $qname, $ipv6);
            }
            return $lines;
        case 'nodata':
            return [sprintf('%s CNAME *.', $qname)];
        case 'passthru':
            return [sprintf('%s CNAME rpz-passthru.', $qname)];
        case 'drop':
            return [sprintf('%s CNAME rpz-drop.', $qname)];
        case 'tcp-only':
            return [sprintf('%s CNAME rpz-tcp-only.', $qname)];
        case 'cname':
            $target = trim((string)$redirect_target);
            if ($target === '') {
                return [];
            }
            return [sprintf('%s CNAME %s.', $qname, rtrim($target, '.'))];
        case 'nxdomain':
        default:
            return [sprintf('%s CNAME .', $qname)];
    }
}

$cfg = Config::getInstance()->object();
$rpz = $cfg->OPNsense->bind->rpz ?? null;
$rpzentry = $cfg->OPNsense->bind->rpzentry ?? null;

if ($rpz === null || empty($rpz->zones) || empty($rpz->zones->zone)) {
    exit(0);
}

// Build zone uuid -> name map for local-type zones only.
$local_zones = [];
foreach ($rpz->zones->zone as $zone) {
    if ((string)$zone->type === 'local' && (string)$zone->enabled === '1') {
        $uuid = (string)$zone->attributes()->uuid;
        $local_zones[$uuid] = (string)$zone->name;
    }
}

if (empty($local_zones)) {
    exit(0);
}

// Group enabled entries by their parent zone uuid.
$entries_by_zone = array_fill_keys(array_keys($local_zones), []);
if ($rpzentry !== null && !empty($rpzentry->entries) && !empty($rpzentry->entries->entry)) {
    foreach ($rpzentry->entries->entry as $entry) {
        if ((string)$entry->enabled !== '1') {
            continue;
        }
        $zone_uuid = (string)$entry->zone;
        if (!isset($entries_by_zone[$zone_uuid])) {
            continue; // entry belongs to a zone that's disabled, feed-type, or deleted
        }
        $qname = trim((string)$entry->qname);
        if ($qname === '') {
            continue;
        }
        $lines = rpz_entry_lines(
            $qname,
            (string)$entry->action,
            (string)$entry->redirect_target,
            (string)($entry->ipv4 ?? ''),
            (string)($entry->ipv6 ?? '')
        );
        foreach ($lines as $line) {
            $entries_by_zone[$zone_uuid][] = $line;
        }
    }
}

$serial = time();

foreach ($local_zones as $uuid => $name) {
    $lines = [
        '$TTL 30',
        "@ SOA localhost. root.localhost. {$serial} 300 1800 604800 30",
        ' NS localhost.',
        '; Generated by rpzLocalGenerate.php — local RPZ zone, GUI-managed via Services > BIND > RPZ',
    ];
    foreach ($entries_by_zone[$uuid] as $line) {
        $lines[] = $line;
    }
    $content = implode("\n", $lines) . "\n";

    $target_file = PRIMARY_DIR . "/{$name}.db";
    $tmp_file = "{$target_file}.tmp";

    file_put_contents($tmp_file, $content);

    // Validate before replacing — a bad row (rare, given the option-field
    // constraints on action/qname) should never leave a broken zone live.
    exec('/usr/local/bin/named-checkzone ' . escapeshellarg($name) . ' ' . escapeshellarg($tmp_file), $output, $result_code);
    if ($result_code !== 0) {
        syslog(LOG_ERR, "rpzLocalGenerate: named-checkzone failed for zone {$name}, keeping previous file: " . implode(' ', $output));
        unlink($tmp_file);
        continue;
    }

    chown($tmp_file, 'bind');
    chgrp($tmp_file, 'bind');
    chmod($tmp_file, 0644);
    rename($tmp_file, $target_file);
}
