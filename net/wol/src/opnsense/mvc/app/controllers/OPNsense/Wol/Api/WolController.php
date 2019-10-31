<?php

/*
 * Copyright (C) 2017 Fabian Franz
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

namespace OPNsense\Wol\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Wol\Wol;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;

class WolController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'wol';
    protected static $internalModelClass = '\OPNsense\Wol\Wol';

    public function setAction()
    {
        $result = array();
        if ($this->request->isPost()) {
            /* input validation */
            $wol = $this->getModel();
            $wolent = $wol->wolentry->Add();
            if ($this->request->hasPost('uuid')) {
                $uuid = $this->request->getPost('uuid');
                $tmp = $wol->getNodeByReference('wolentry.' . $uuid);
                if ($tmp) {
                    $wolent = $tmp;
                    $this->wakeHostByNode($wolent, $result);
                }
            } else {
                $wolent->setNodes($this->request->getPost('wake'));
                if ($wol->performValidation()->count() == 0) {
                    $this->wakeHostByNode($wolent, $result);
                }
            }
        }
        return $result;
    }

    public function delHostAction($uuid)
    {
        $this->delBase('wolentry', $uuid);
    }
    public function searchHostAction()
    {
        return $this->searchBase('wolentry', array("interface", "mac", "descr"));
    }
    public function getHostAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('host', 'wolentry', $uuid);
    }
    public function getwakeAction()
    {
        return $this->getBase('wake', 'wolentry', null);
    }
    public function addHostAction()
    {
        return $this->addBase('host', 'wolentry');
    }
    public function setHostAction($uuid)
    {
        return $this->setBase('host', 'wolentry', $uuid);
    }
    public function wakeallAction()
    {
        if (!$this->request->isPost()) {
            return array('error' => 'Must be called via POST');
        }
        $results = array('results' => array());
        foreach ($this->getModel()->wolentry->iterateItems() as $wolent) {
            $result = array('mac' => (string)$wolent->mac);
            $this->wakeHostByNode($wolent, $result);
            $results['results'][] = $result;
        }
        return $results;
    }
    private function wakeHostByNode($wolent, &$result)
    {
        $backend = new Backend();
        /* determine broadcast address */
        $cidr = $this->getInterfaceSubnet($wolent->interface);
        $ipaddr = $this->getInterfaceIP($wolent->interface);
        if (empty($cidr) || empty($ipaddr)) {
            $result['status'] = 'error';
            $result['error_msg'] = 'Incorrect IPv4 configuration on interface';
            return $result;
        }
        $broadcast_ip = escapeshellarg($this->calculateSubnetBroadcast($ipaddr, $cidr));
        $result['status'] = trim($backend->configdRun("wol wake {$broadcast_ip} " . escapeshellarg((string)$wolent->mac)));
    }
    private function getInterfaceIP($if)
    {
        $cfg = Config::getInstance()->object();
        try {
            $tmp = (string)$cfg->interfaces->{$if}->ipaddr;
          // for example DHCP
            if (!filter_var($tmp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return null;
            }
            return $tmp;
        } catch (Exception $e) {
            return null;
        }
    }
    private function getInterfaceSubnet($if)
    {
        $cfg = Config::getInstance()->object();
        try {
            return (string)$cfg->interfaces->{$if}->subnet;
        } catch (Exception $e) {
            return null;
        }
    }
    private function calculateSubnetBroadcast($ip_addr, $cidr)
    {
        // TODO undefined offset
        $parts = explode('.', $ip_addr);
        $int_ip = ((int)$parts[0]) << 24 | ((int)$parts[1]) << 16 | ((int)$parts[2]) << 8 | (int)$parts[3];
        $hostmask = (2 << (31 - $cidr)) - 1;
        $int_bcast = $int_ip | $hostmask;
        return implode('.', array(
          ($int_bcast >> 24) & 255,
          ($int_bcast >> 16) & 255,
          ($int_bcast >> 8) & 255,
          $int_bcast & 255,
        ));
    }
}
