<?php

/**
 *    Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\QFeeds\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'connect';
    protected static $internalModelClass = 'OPNsense\QFeeds\Connector';

    public function reconfigureAction()
    {
        $backend = new Backend();
        $res = trim($backend->configdRun('template reload OPNsense/QFeeds'));
        if (strtolower($res) != 'ok') {
            throw new UserException(sprintf(gettext("Unable to update settings (%s)"), $res));
        }
        $res = trim($backend->configdRun('qfeeds reconfigure'));
        if (strpos($res, 'EXIT OK') === false) {
            throw new UserException($res);
        }
        return ['status' => 'ok', 'output' => $res];
    }

    public function searchFeedsAction()
    {
        $records = [];
        $data = json_decode((new Backend())->configdRun('qfeeds info') ?? '[]', true);
        if (!empty($data) && !empty($data['feeds'])) {
            $records = $data['feeds'];
            foreach ($records as &$record) {
                $record['licensed'] = $record['licensed'] ? '1' : '0';
            }
        }
        return $this->searchRecordsetBase($records);
    }

    public function searchEventsAction()
    {
        $records = [];
        $ifnames = [];
        foreach (Config::getInstance()->object()->interfaces->children() as $key => $node) {
            if (!empty((string)$node->if)) {
                $ifnames[(string)$node->if] = !empty((string)($node->descr)) ? (string)($node->descr) : strtoupper($key);
            }
        }
        $data = json_decode((new Backend())->configdRun('qfeeds logs') ?? '[]', true);
        if (!empty($data) && !empty($data['rows'])) {
            foreach ($data['rows'] as $row) {
                $records[] = [
                    'timestamp' => $row[0],
                    'interface' => $ifnames[$row[1]] ?? $row[1],
                    'direction' => $row[2],
                    'source' => $row[3],
                    'destination' => $row[4],
                ];
            }
        }
        return $this->searchRecordsetBase($records);
    }

    public function statsAction()
    {
        $stats = json_decode((new Backend())->configdRun('qfeeds stats'), true);
        if (!empty($stats) && !empty($stats['feeds'])) {
            $info = json_decode((new Backend())->configdRun('qfeeds info'), true);
            if (!empty($info) && !empty($info['feeds'])) {
                $feeds = [];
                foreach ($info['feeds'] as $feed) {
                    $feeds[$feed['feed_type']] = $feed;
                }
                foreach ($stats['feeds'] as &$feed) {
                    if (isset($feeds[$feed['name']])) {
                        $tmp = $feeds[$feed['name']];
                        $feed['updated_at'] = $tmp['updated_at'];
                        $feed['next_update'] = $tmp['next_update'];
                        $feed['licensed'] = $tmp['licensed'];
                    }
                }
            }
            // Add license information from company_info if available
            if (!empty($info['company_info'])) {
                $stats['license'] = [
                    'name' => $info['company_info']['license_name'] ?? null,
                    'expiry_date' => $info['company_info']['license_expiry_date'] ?? null
                ];
            }
        }
        return $stats;
    }
}
