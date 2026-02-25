#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2016-2026 Frank Wall
 * Copyright (C) 2015 Deciso B.V.
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

// Use legacy code to export certificates to the filesystem.
require_once("config.inc");

use OPNsense\Core\Config;

$export_path = '/tmp/haproxy/mapfiles/';

// traverse HAProxy map files
$configObj = Config::getInstance()->object();
if (isset($configObj->OPNsense->HAProxy->mapfiles)) {
    foreach ($configObj->OPNsense->HAProxy->mapfiles->children() as $mapfile) {
        $mf_name = (string)$mapfile->name;
        $mf_id = (string)$mapfile->id;
        $mf_url = (string)$mapfile->url;
        if ($mf_id != "") {
            $mf_filename = $export_path . $mf_id . ".txt";
            // Download file from URL (if URL was provided).
            try {
                if ($mf_url == "") {
                    throw new \Exception("no URL provided");
                }
                $fp = fopen($mf_filename, 'wb');
                if ($fp === false) {
                    throw new \Exception("unable to open {$mf_filename} for writing");
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $mf_url);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_FAILONERROR, true);
                curl_setopt($ch, CURLOPT_FILE, $fp);

                if (!curl_exec($ch)) {
                    throw new \Exception("download error: " . curl_error($ch));
                }
                echo "map file downloaded to " . $mf_filename . "\n";
            } catch (\Exception $e) {
                // Show error message only if URL was specified.
                if ($mf_url != "") {
                    echo "download of map file failed, error: " . $e->getMessage() . "\n";
                    echo "trying to fill map file with fallback content\n";
                    $mf_content = "# NOTE: Download failed, this is the fallback content.\n";
                } else {
                    $mf_content = '';
                }

                // Write contents to map file.
                // This is also used as a fallback if map file download fails.
                $mf_content = $mf_content . htmlspecialchars_decode(str_replace("\r", "", (string)$mapfile->content));
                file_put_contents($mf_filename, $mf_content);
                echo "map file exported to " . $mf_filename . "\n";
            } finally {
                if (isset($ch)) {
                    curl_close($ch);
                }
                if (isset($fp) && is_resource($fp)) {
                    fclose($fp);
                }
                chmod($mf_filename, 0600);
                chown($mf_filename, 'www');
            }
        }
    }
}
