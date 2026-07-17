<?php

/*
    Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
    All rights reserved.
    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:
    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.
    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

namespace OPNsense\Bind;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

class Domain extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        foreach ($this->domains->domain->iterateItems() as $domain) {
            if ((string)$domain->type !== 'reverse') {
                if (trim((string)$domain->domainname) === '') {
                    $messages->appendMessage(new Message(
                        gettext('A zone name is required.'),
                        $domain->domainname->getInternalXMLTagName()
                    ));
                }
                continue;
            }
            $subnet = (string)$domain->source_subnet;
            $parts = explode('/', $subnet, 2);
            $prefix = isset($parts[1]) ? (int)$parts[1] : -1;
            $alignment = strpos($subnet, ':') === false ? 8 : 4;
            if ($subnet === '' || $prefix < 0 || $prefix % $alignment !== 0) {
                $messages->appendMessage(new Message(
                    gettext('Reverse zones require an IPv4 octet-aligned or IPv6 nibble-aligned source subnet.'),
                    $domain->source_subnet->getInternalXMLTagName()
                ));
            }
        }
        return $messages;
    }

    /**
     * Return the ARPA zone name for an IPv4 octet-aligned or IPv6 nibble-aligned subnet.
     *
     * @param string $subnet CIDR notation
     * @return string|null reverse zone name or null for invalid input
     */
    public static function reverseZoneName($subnet)
    {
        $parts = explode('/', $subnet, 2);
        if (count($parts) !== 2 || !is_numeric($parts[1])) {
            return null;
        }

        $packed = inet_pton($parts[0]);
        if ($packed === false) {
            return null;
        }

        $prefix = (int)$parts[1];
        if (strlen($packed) === 4 && $prefix >= 0 && $prefix <= 32 && $prefix % 8 === 0) {
            $octets = array_slice(unpack('C*', $packed), 0, $prefix / 8);
            return empty($octets)
                ? 'in-addr.arpa'
                : implode('.', array_reverse($octets)) . '.in-addr.arpa';
        }
        if (strlen($packed) === 16 && $prefix >= 0 && $prefix <= 128 && $prefix % 4 === 0) {
            $hex = substr(bin2hex($packed), 0, $prefix / 4);
            return empty($hex)
                ? 'ip6.arpa'
                : implode('.', str_split(strrev($hex))) . '.ip6.arpa';
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function serializeToConfig($validateFullModel = false, $disable_validation = false)
    {
        $serialsToSet = array();
        // collected changed records
        foreach ($this->getFlatNodes() as $key => $node) {
            if ($node->isFieldChanged() && (string)$node !== "") {
                $domain = $node->getParentNode();
                if (empty($serialsToSet[$domain->getAttribute('uuid')])) {
                    $serialsToSet[$domain->getAttribute('uuid')] = $domain;
                }
            }
        }
        // new serials on changed records
        foreach ($serialsToSet as $domain) {
            $domain->serial = (string)date("ymdHi");
        }
        return parent::serializeToConfig($validateFullModel, $disable_validation);
    }

    /**
     * @param $uuid string domain uuid to update
     * @return Domain
     */
    public function updateSerial($uuid)
    {
        foreach ($this->domains->domain->iterateItems() as $domain) {
            if ($domain->getAttribute('uuid') == $uuid) {
                $domain->serial = (string)date("ymdHi");
                return $this;
            }
        }
        return $this;
    }
}
