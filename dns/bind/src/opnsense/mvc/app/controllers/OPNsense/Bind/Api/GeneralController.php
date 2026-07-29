<?php

/**
 *    Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class GeneralController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\Bind\General';
    protected static $internalModelName = 'general';

    public function zonetestAction($zonename = null)
    {
        $response = "request error";
        if ($this->request->hasPost("zone")) {
            $zonename = $this->request->getPost("zone");
            $backend = new Backend();
            $response = trim($backend->configdpRun("bind zone check", [$zonename]));
        }
        return array("response" => $response);
    }

    public function zoneshowAction($zonename = null)
    {
        $response = "request error";
        if ($this->request->hasPost("zone")) {
            $zonename = $this->request->getPost("zone");
            $backend = new Backend();
            $response = json_decode($backend->configdpRun("bind zone show", [$zonename]), true);
        }
        return $response;
    }

    public function listSubnetsAction()
    {
        $result = ['subnets' => []];
        $seenNetworks = [];
        $backend = new Backend();
        $ifconfig = json_decode($backend->configdRun('interface list ifconfig'), true) ?? [];
        $cfg = \OPNsense\Core\Config::getInstance()->object();
        $intfmap = [];
        if (isset($cfg->interfaces)) {
            foreach ($cfg->interfaces->children() as $key => $node) {
                $intfmap[(string)$node->if] = !empty((string)$node->descr) ? (string)$node->descr : strtoupper($key);
            }
        }

        foreach ($ifconfig as $if => $details) {
            foreach (['ipv4', 'ipv6'] as $family) {
                foreach ($details[$family] ?? [] as $addr) {
                    if (empty($addr['ipaddr']) || !isset($addr['subnetbits'])) {
                        continue;
                    }
                    if (($family === 'ipv4' && strpos($addr['ipaddr'], '127.') === 0) ||
                        ($family === 'ipv6' && $addr['ipaddr'] === '::1')) {
                        continue;
                    }
                    $network = $this->networkAddress($addr['ipaddr'], $addr['subnetbits']);
                    if ($network === null || isset($seenNetworks[$network])) {
                        continue;
                    }
                    $seenNetworks[$network] = true;
                    $result['subnets'][] = [
                        'label' => ($intfmap[$if] ?? $if) . ' - ' . $network,
                        'value' => $network,
                        'family' => $family,
                        'interface' => $if,
                    ];
                }
            }
        }
        return $result;
    }

    private function networkAddress($address, $prefix)
    {
        if (!is_numeric($prefix) || ($packed = inet_pton($address)) === false) {
            return null;
        }
        $prefix = (int)$prefix;
        if ($prefix < 0 || $prefix > strlen($packed) * 8) {
            return null;
        }
        $remainingBits = $prefix;
        $network = '';
        for ($index = 0; $index < strlen($packed); ++$index) {
            $bits = min(max($remainingBits, 0), 8);
            $mask = $bits === 0 ? 0 : (0xff << (8 - $bits)) & 0xff;
            $network .= chr(ord($packed[$index]) & $mask);
            $remainingBits -= 8;
        }
        return inet_ntop($network) . '/' . $prefix;
    }
}
