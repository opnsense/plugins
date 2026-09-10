<?php

/*
 * Copyright (C) 2025 OPNsense Community
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

namespace OPNsense\Xproxy\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Xproxy\Xproxy;

class ImportController extends ApiControllerBase
{
    private const MAX_IMPORT_BYTES = 2097152;

    /**
     * @param array<int, array<string, mixed>> $servers
     * @return array{added: int, skipped: int}
     */
    private function mergeServersIntoModel(Xproxy $mdl, array $servers): array
    {
        $existing = [];
        foreach ($mdl->servers->server->iterateItems() as $item) {
            $ru = (string)$item->raw_uri;
            if ($ru !== '') {
                $existing[$ru] = true;
            }
        }
        $added = 0;
        $skipped = 0;
        $fieldMap = [
            'enabled', 'description', 'protocol', 'address', 'port',
            'user_id', 'password', 'encryption', 'flow', 'transport',
            'transport_host', 'transport_path', 'security', 'sni',
            'fingerprint', 'alpn', 'reality_pubkey', 'reality_short_id', 'raw_uri',
        ];
        foreach ($servers as $srv) {
            if (!is_array($srv)) {
                continue;
            }
            $raw = isset($srv['raw_uri']) ? (string)$srv['raw_uri'] : '';
            if ($raw !== '' && !empty($existing[$raw])) {
                $skipped++;
                continue;
            }
            $node = $mdl->servers->server->Add();
            foreach ($fieldMap as $field) {
                if (!array_key_exists($field, $srv) || $srv[$field] === '' || $srv[$field] === null) {
                    continue;
                }
                $node->$field = (string)$srv[$field];
            }
            if ($raw !== '') {
                $existing[$raw] = true;
            }
            $added++;
        }
        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * Import servers from proxy URI strings (vless://, vmess://, ss://, trojan://).
     */
    public function urisAction()
    {
        $result = array("result" => "failed", "count" => 0);
        if ($this->request->isPost()) {
            $uris = $this->request->getPost('uris');
            if ($uris === null) {
                $uris = '';
            }
            if (!is_string($uris)) {
                $uris = '';
            }
            if ($uris !== '') {
                if (strlen($uris) > self::MAX_IMPORT_BYTES) {
                    $result["message"] = "Import payload too large (max 2 MiB).";
                    return $result;
                }
                $tmpFile = tempnam('/tmp', 'xproxy_import_');
                if ($tmpFile === false) {
                    $result["message"] = "Could not create temporary file.";
                    return $result;
                }
                chmod($tmpFile, 0600);
                try {
                    file_put_contents($tmpFile, $uris);
                    $backend = new Backend();
                    $response = trim($backend->configdRun("xproxy import " . escapeshellarg($tmpFile)));
                } finally {
                    @unlink($tmpFile);
                }
                $parsed = json_decode($response, true);
                if (is_array($parsed) && isset($parsed['servers']) && is_array($parsed['servers'])) {
                    $mdl = new Xproxy();
                    $merge = $this->mergeServersIntoModel($mdl, $parsed['servers']);
                    if ($merge['added'] > 0) {
                        if (empty((string)$mdl->general->active_server)) {
                            foreach ($mdl->servers->server->iterateItems() as $srvUuid => $srvItem) {
                                $mdl->general->active_server = $srvUuid;
                                $result["auto_selected"] = (string)$srvItem->description;
                                break;
                            }
                        }
                        $mdl->serializeToConfig();
                        Config::getInstance()->save();
                        $result["result"] = "saved";
                        $result["count"] = $merge['added'];
                        if ($merge['skipped'] > 0) {
                            $result["skipped"] = $merge['skipped'];
                        }
                    } else {
                        $result["message"] = "No new servers to add (duplicates skipped).";
                        if ($merge['skipped'] > 0) {
                            $result["skipped"] = $merge['skipped'];
                        }
                    }
                    if (!empty($parsed['errors'])) {
                        $result["errors"] = $parsed['errors'];
                    }
                } else {
                    $result["message"] = "Failed to parse import response.";
                }
            } else {
                $result["message"] = "No URIs provided.";
            }
        }
        return $result;
    }

}
