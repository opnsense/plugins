<?php

/*
 * Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
 * Copyright (C) 2019 Deciso B.V.
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

namespace OPNsense\Bind\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Bind\Domain;

class DomainController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'domain';
    protected static $internalModelClass = '\OPNsense\Bind\Domain';

    /* XXX backwards-compatibility for 22.7 and below */
    public function searchMasterDomainAction()
    {
        return $this->searchPrimaryDomainAction();
    }

    /* XXX backwards-compatibility for 22.7 and below */
    public function searchSlaveDomainAction()
    {
        return $this->searchSecondaryDomainAction();
    }

    public function searchPrimaryDomainAction()
    {
        return $this->searchBase(
            'domains.domain',
            [ 'enabled', 'type', 'domainname', 'ttl', 'refresh', 'retry', 'expire', 'negative' ],
            'domainname',
            function ($record) {
                return $record->type->getNodeData()['primary']['selected'] === 1;
            }
        );
    }

    public function searchSecondaryDomainAction()
    {
        return $this->searchBase(
            'domains.domain',
            [ 'enabled', 'type', 'domainname', 'primaryip' ],
            'domainname',
            function ($record) {
                return $record->type->getNodeData()['secondary']['selected'] === 1;
            }
        );
    }

    public function searchForwardDomainAction()
    {
        return $this->searchBase(
            'domains.domain',
            [ 'enabled', 'type', 'domainname', 'forwardserver' ],
            'domainname',
            function ($record) {
                return $record->type->getNodeData()['forward']['selected'] === 1;
            }
        );
    }

    /**
     * search reverse domains
     * @return array search results
     */
    public function searchReverseDomainAction()
    {
        $result = $this->searchBase(
            'domains.domain',
            ['enabled', 'type', 'domainname', 'source_subnet'],
            "domainname",
            function ($record) {
                return $record->type->getNodeData()['reverse']['selected'] === 1;
            }
        );
        // Add interface description to each result
        foreach ($result['rows'] as &$row) {
            $row['interface'] = $this->getInterfaceFromSubnet($row['source_subnet'] ?? '');
        }
        return $result;
    }

    public function getDomainAction($uuid = null)
    {
        return $this->getBase('domain', 'domains.domain', $uuid);
    }

    public function addPrimaryDomainAction($uuid = null)
    {
        return $this->addBase('domain', 'domains.domain', ['type' => 'primary']);
    }

    public function addSecondaryDomainAction($uuid = null)
    {
        return $this->addBase('domain', 'domains.domain', ['type' => 'secondary']);
    }

    public function addForwardDomainAction($uuid = null)
    {
        return $this->addBase('domain', 'domains.domain', ['type' => 'forward']);
    }

    /**
     * add reverse domain
     * @param string $uuid unique identifier
     * @return array status message
     */
    public function addReverseDomainAction($uuid = null)
    {
        $result = $this->addBase(
            'domain',
            'domains.domain',
            ['type' => 'reverse']
        );
        return $result;
    }

    public function delDomainAction($uuid)
    {
        return $this->delBase('domains.domain', $uuid);
    }

    public function setDomainAction($uuid = null)
    {
        return $this->setBase('domain', 'domains.domain', $uuid);
    }

    public function toggleDomainAction($uuid)
    {
        return $this->toggleBase('domains.domain', $uuid);
    }

    /**
     * Generate reverse-zone names from their selected source subnet.
     *
     * @param object $node domain model item
     * @return void
     */
    protected function setBaseHook($node)
    {
        if ((string)$node->type === 'reverse') {
            $zoneName = Domain::reverseZoneName((string)$node->source_subnet);
            if ($zoneName !== null) {
                $node->domainname = $zoneName;
            }
        }
    }

    /**
     * Get interface description from subnet
     * @param string $subnet CIDR notation (e.g., "192.168.1.0/24")
     * @return string interface description or empty string
     */
    private function getInterfaceFromSubnet($subnet)
    {
        if (empty($subnet)) {
            return '';
        }

        $backend = new \OPNsense\Core\Backend();
        $ifconfig_json = $backend->configdRun('interface list ifconfig');
        $ifconfig = json_decode($ifconfig_json, true) ?? [];

        foreach ($ifconfig as $if => $details) {
            foreach (['ipv4', 'ipv6'] as $family) {
                if (!empty($details[$family])) {
                    foreach ($details[$family] as $addr) {
                        if (!empty($addr['ipaddr']) && !empty($addr['subnetbits'])) {
                            $network = $addr['ipaddr'] . '/' . $addr['subnetbits'];
                            if ($network === $subnet) {
                                // Get interface description from config
                                $cfg = \OPNsense\Core\Config::getInstance()->object();
                                if (isset($cfg->interfaces)) {
                                    foreach ($cfg->interfaces->children() as $key => $node) {
                                        if ((string)$node->if === $if) {
                                            return !empty((string)$node->descr) ? (string)$node->descr : strtoupper($key);
                                        }
                                    }
                                }
                                return $if;
                            }
                        }
                    }
                }
            }
        }
        return '';
    }
}
