<?php

/**
 *    Copyright (C) 2026 Bryan Wiegand <inbox@kw-ventures.com>
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

namespace OPNsense\Bind\Api;

use OPNsense\Base\ApiControllerBase;

class DhcprecordController extends ApiControllerBase
{
    /**
     * Search DHCP records from watcher state file
     * @return array search results with rows, total, rowCount, current
     */
    public function searchRecordAction()
    {
        $stateFile = '/var/cache/bind/dhcplease_state.json';

        // Read state file
        $records = [];
        if (file_exists($stateFile)) {
            $stateData = json_decode(file_get_contents($stateFile), true);
            if (is_array($stateData)) {
                foreach ($stateData as $stateKey => $lease) {
                    if (strpos($stateKey, '|') === false || empty($lease['suffix'])) {
                        continue;
                    }
                    if (isset($lease['address']) && isset($lease['hostname']) && isset($lease['ends'])) {
                        $records[] = [
                            'hostname' => $lease['hostname'],
                            'domain' => $lease['suffix'] ?? '',
                            'address' => $lease['address'],
                            'mac' => $lease['mac'] ?? '',
                            'ends' => date('Y-m-d H:i:s', $lease['ends']),
                            'source' => $lease['source'] ?? 'unknown'
                        ];
                    }
                }
            }
        }

        // Use standard OPNsense search helper
        return $this->searchRecordsetBase($records, ['hostname', 'domain', 'address', 'mac', 'source'], 'hostname');
    }
}
