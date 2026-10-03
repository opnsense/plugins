<?php

/**
 *    Copyright (C) 2025 C. Hall (chall37@users.noreply.github.com)
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

namespace OPNsense\DnsmasqToUnbound\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

/**
 * Class ServiceController
 * @package OPNsense\DnsmasqToUnbound\Api
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\DnsmasqToUnbound\DnsmasqToUnbound';
    protected static $internalServiceEnabled = 'enabled';
    protected static $internalServiceTemplate = 'OPNsense/DnsmasqToUnbound';
    protected static $internalServiceName = 'dnsmasqtounbound';

    /**
     * Search/list current DNS records registered from dnsmasq
     * @return array
     */
    public function searchrecordsAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('dnsmasqtounbound listrecords');
        $data = json_decode($response, true);
        if ($data === null || !isset($data['rows'])) {
            return ['total' => 0, 'rowCount' => 0, 'current' => 1, 'rows' => []];
        }

        $rows = $data['rows'];

        // Handle sorting from bootgrid
        if ($this->request->isPost()) {
            $sortColumn = null;
            $sortOrder = 'asc';
            $post = $this->request->getPost();
            if (isset($post['sort']) && is_array($post['sort'])) {
                foreach ($post['sort'] as $col => $order) {
                    $sortColumn = $col;
                    $sortOrder = strtolower($order) === 'desc' ? 'desc' : 'asc';
                    break;
                }
            }

            if ($sortColumn !== null) {
                usort($rows, function ($a, $b) use ($sortColumn, $sortOrder) {
                    $valA = isset($a[$sortColumn]) ? (string)$a[$sortColumn] : '';
                    $valB = isset($b[$sortColumn]) ? (string)$b[$sortColumn] : '';

                    // Check for empty/null values (treat '-' as empty)
                    $emptyA = ($valA === '' || $valA === '-');
                    $emptyB = ($valB === '' || $valB === '-');

                    // Empty values go to end on asc, beginning on desc
                    if ($emptyA && !$emptyB) {
                        return $sortOrder === 'asc' ? 1 : -1;
                    }
                    if (!$emptyA && $emptyB) {
                        return $sortOrder === 'asc' ? -1 : 1;
                    }
                    if ($emptyA && $emptyB) {
                        return 0;
                    }

                    // IP address sorting
                    if ($sortColumn === 'ip') {
                        $ipA = ip2long($valA);
                        $ipB = ip2long($valB);
                        if ($ipA !== false && $ipB !== false) {
                            $cmp = $ipA - $ipB;
                            return $sortOrder === 'desc' ? -$cmp : $cmp;
                        }
                    }

                    // Default string comparison
                    $cmp = strcmp(strtolower($valA), strtolower($valB));
                    return $sortOrder === 'desc' ? -$cmp : $cmp;
                });
            }
        }

        return [
            'total' => count($rows),
            'rowCount' => count($rows),
            'current' => 1,
            'rows' => $rows
        ];
    }

    /**
     * Get hash of current DNS records for change detection
     * @return array
     */
    public function recordshashAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun('dnsmasqtounbound recordshash');
        $data = json_decode($response, true);
        if ($data === null) {
            return ['hash' => ''];
        }
        return $data;
    }
}
