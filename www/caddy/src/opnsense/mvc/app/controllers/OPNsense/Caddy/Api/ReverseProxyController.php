<?php

/*
 * Copyright (C) 2023-2024 Cedrik Pischem
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

namespace OPNsense\Caddy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class ReverseProxyController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'caddy';
    protected static $internalModelClass = 'OPNsense\Caddy\Caddy';
    protected static $internalModelUseSafeDelete = true;

    /**
     * Function for search filter dropdown
     *
     * @return array containing rows of domain and port combinations.
     */
    public function getAllReverseDomainsAction()
    {
        $result = array("rows" => array());

        $mdlCaddy = new \OPNsense\Caddy\Caddy();
        $reverseNodes = $mdlCaddy->reverseproxy->reverse->iterateItems();

        foreach ($reverseNodes as $item) {
            if (!empty($item->FromDomain)) {
                // Conditionally concatenate port if it exists
                $domain = (string)$item->FromDomain;
                $port = (string)$item->FromPort;
                $combinedDomainPort = $domain . (!empty($port) ? ':' . $port : '');

                $result['rows'][] = array(
                    'id' => (string)$item->getAttributes()['uuid'],
                    'domainPort' => $combinedDomainPort  // Combined domain and port, conditionally adding port
                );
            }
        }

        return $result;
    }

    /**
     * Build a UUID filter function.
     *
     * @param array $uuidList Array of UUIDs to match.
     * @param string|null $referenceField Optional field to extract UUID from.
     *
     * @return callable|null
     */
    private function buildFilterFunction(array $uuidList, ?string $referenceField = null): ?callable
    {
        if (empty($uuidList)) {
            return null;
        }

        return function ($record) use ($uuidList, $referenceField): bool {
            $uuid = $referenceField !== null
                ? (string)($record->{$referenceField} ?? '')
                : (string)($record->getAttributes()['uuid'] ?? '');

            return in_array($uuid, $uuidList, true);
        };
    }

    // ReverseProxy Section

    public function searchReverseProxyAction()
    {
        $reverseUuids = $this->request->get('reverseUuids') ?? [];
        $filter_funct = $this->buildFilterFunction($reverseUuids);
        return $this->searchBase('reverseproxy.reverse', null, null, $filter_funct);
    }

    public function setReverseProxyAction($uuid)
    {
        return $this->setBase("reverse", "reverseproxy.reverse", $uuid);
    }

    public function addReverseProxyAction()
    {
        return $this->addBase("reverse", "reverseproxy.reverse");
    }

    public function getReverseProxyAction($uuid = null)
    {
        return $this->getBase("reverse", "reverseproxy.reverse", $uuid);
    }

    public function delReverseProxyAction($uuid)
    {
        return $this->delBase("reverseproxy.reverse", $uuid);
    }

    public function toggleReverseProxyAction($uuid, $enabled = null)
    {
        return $this->toggleBase("reverseproxy.reverse", $uuid, $enabled);
    }


    // Subdomain Section

    public function searchSubdomainAction()
    {
        $reverseUuids = $this->request->get('reverseUuids') ?? [];
        $filter_funct = $this->buildFilterFunction($reverseUuids, 'reverse');
        return $this->searchBase('reverseproxy.subdomain', null, null, $filter_funct);
    }

    public function setSubdomainAction($uuid)
    {
        return $this->setBase("subdomain", "reverseproxy.subdomain", $uuid);
    }

    public function addSubdomainAction()
    {
        return $this->addBase("subdomain", "reverseproxy.subdomain");
    }

    public function getSubdomainAction($uuid = null)
    {
        return $this->getBase("subdomain", "reverseproxy.subdomain", $uuid);
    }

    public function delSubdomainAction($uuid)
    {
        return $this->delBase("reverseproxy.subdomain", $uuid);
    }

    public function toggleSubdomainAction($uuid, $enabled = null)
    {
        return $this->toggleBase("reverseproxy.subdomain", $uuid, $enabled);
    }


    // Handler Section

    public function searchHandleAction()
    {
        $reverseUuids = $this->request->get('reverseUuids') ?? [];
        $filter_funct = $this->buildFilterFunction($reverseUuids, 'reverse');
        return $this->searchBase('reverseproxy.handle', null, null, $filter_funct);
    }

    public function setHandleAction($uuid)
    {
        return $this->setBase("handle", "reverseproxy.handle", $uuid);
    }

    public function addHandleAction()
    {
        return $this->addBase("handle", "reverseproxy.handle");
    }

    public function getHandleAction($uuid = null)
    {
        return $this->getBase("handle", "reverseproxy.handle", $uuid);
    }

    public function delHandleAction($uuid)
    {
        return $this->delBase("reverseproxy.handle", $uuid);
    }

    public function toggleHandleAction($uuid, $enabled = null)
    {
        return $this->toggleBase("reverseproxy.handle", $uuid, $enabled);
    }

    // AccessList Section

    public function searchAccessListAction()
    {
        return $this->searchBase("reverseproxy.accesslist");
    }

    public function setAccessListAction($uuid)
    {
        return $this->setBase("accesslist", "reverseproxy.accesslist", $uuid);
    }

    public function addAccessListAction()
    {
        return $this->addBase("accesslist", "reverseproxy.accesslist");
    }

    public function getAccessListAction($uuid = null)
    {
        return $this->getBase("accesslist", "reverseproxy.accesslist", $uuid);
    }

    public function delAccessListAction($uuid)
    {
        return $this->delBase("reverseproxy.accesslist", $uuid);
    }


    // BasicAuth Section

    public function searchBasicAuthAction()
    {
        return $this->searchBase("reverseproxy.basicauth");
    }

    public function setBasicAuthAction($uuid)
    {
        if ($this->request->isPost()) {
            $postData = $this->request->getPost();
            if (
                isset($postData['basicauth']['basicauthpass'])
                && !empty(trim($postData['basicauth']['basicauthpass']))
            ) {
                $plainPassword = $postData['basicauth']['basicauthpass'];
                $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
                $_POST['basicauth']['basicauthpass'] = $hashedPassword;
            }
        }

        return $this->setBase("basicauth", "reverseproxy.basicauth", $uuid);
    }

    public function addBasicAuthAction()
    {
        if ($this->request->isPost()) {
            $postData = $this->request->getPost();
            if (
                isset($postData['basicauth']['basicauthpass'])
                && !empty(trim($postData['basicauth']['basicauthpass']))
            ) {
                $plainPassword = $postData['basicauth']['basicauthpass'];
                $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
                $_POST['basicauth']['basicauthpass'] = $hashedPassword;
            }
        }

        return $this->addBase("basicauth", "reverseproxy.basicauth");
    }

    public function getBasicAuthAction($uuid = null)
    {
        return $this->getBase("basicauth", "reverseproxy.basicauth", $uuid);
    }

    public function delBasicAuthAction($uuid)
    {
        return $this->delBase("reverseproxy.basicauth", $uuid);
    }


    // Header Section

    public function searchHeaderAction()
    {
        return $this->searchBase("reverseproxy.header");
    }

    public function setHeaderAction($uuid)
    {
        return $this->setBase("header", "reverseproxy.header", $uuid);
    }

    public function addHeaderAction()
    {
        return $this->addBase("header", "reverseproxy.header");
    }

    public function getHeaderAction($uuid = null)
    {
        return $this->getBase("header", "reverseproxy.header", $uuid);
    }

    public function delHeaderAction($uuid)
    {
        return $this->delBase("reverseproxy.header", $uuid);
    }


    // Layer4 Proxy Section

    public function searchLayer4Action()
    {
        return $this->searchBase("reverseproxy.layer4");
    }

    public function setLayer4Action($uuid)
    {
        return $this->setBase("layer4", "reverseproxy.layer4", $uuid);
    }

    public function addLayer4Action()
    {
        return $this->addBase("layer4", "reverseproxy.layer4");
    }

    public function getLayer4Action($uuid = null)
    {
        return $this->getBase("layer4", "reverseproxy.layer4", $uuid);
    }

    public function delLayer4Action($uuid)
    {
        return $this->delBase("reverseproxy.layer4", $uuid);
    }

    public function toggleLayer4Action($uuid, $enabled = null)
    {
        return $this->toggleBase("reverseproxy.layer4", $uuid, $enabled);
    }


    // Layer4 OpenVPN Section

    public function searchLayer4OpenvpnAction()
    {
        return $this->searchBase("reverseproxy.layer4openvpn");
    }

    public function setLayer4OpenvpnAction($uuid)
    {
        return $this->setBase("layer4openvpn", "reverseproxy.layer4openvpn", $uuid);
    }

    public function addLayer4OpenvpnAction()
    {
        return $this->addBase("layer4openvpn", "reverseproxy.layer4openvpn");
    }

    public function getLayer4OpenvpnAction($uuid = null)
    {
        return $this->getBase("layer4openvpn", "reverseproxy.layer4openvpn", $uuid);
    }

    public function delLayer4OpenvpnAction($uuid)
    {
        return $this->delBase("reverseproxy.layer4openvpn", $uuid);
    }

    public function toggleLayer4OpenvpnAction($uuid, $enabled = null)
    {
        return $this->toggleBase("reverseproxy.layer4openvpn", $uuid, $enabled);
    }
}
