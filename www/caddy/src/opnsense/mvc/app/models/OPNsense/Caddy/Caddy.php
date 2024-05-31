<?php

/**
 *    Copyright (C) 2023-2024 Cedrik Pischem
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

namespace OPNsense\Caddy;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Config;

class Caddy extends BaseModel
{
    // 1. Check domain-port combinations
    // 2. Check subdomain-port combinations
    private function checkForUniquePortCombos($items, $messages)
    {
        $combos = [];
        foreach ($items as $item) {
            $key = $item->__reference; // Dynamic key based on item reference
            $fromDomainOrSubdomain = (string) $item->FromDomain;
            $fromPort = (string) $item->FromPort;

            if ($fromPort === '') {
                $defaultPorts = ['80', '443'];
            } else {
                $defaultPorts = [$fromPort];
            }

            foreach ($defaultPorts as $port) {
                // Create a unique key for domain/subdomain-port combination
                $comboKey = $fromDomainOrSubdomain . ':' . $port;

                // Check for duplicate combinations
                if (isset($combos[$comboKey])) {
                    // Use dynamic $key for message referencing
                    $messages->appendMessage(new Message(
                        sprintf(gettext("Duplicate entry: The combination of '%s' and port '%s' is already used. Each combination of domain/subdomain and port must be unique."), $fromDomainOrSubdomain, $port),
                        $key . ".FromDomain", // Adjusted to use dynamic key
                        "DuplicateDomainPort"
                    ));
                } else {
                    $combos[$comboKey] = true;
                }
            }
        }
    }

    // 3. Check that subdomains are under a wildcard or exact domain
    private function checkSubdomainsAgainstDomains($subdomains, $domains, $messages)
    {
        $wildcardDomainList = [];
        foreach ($domains as $domain) {
            if ((string) $domain->enabled === '1') {
                $domainName = (string) $domain->FromDomain;
                if (str_starts_with($domainName, '*.')) {
                    $wildcardBase = substr($domainName, 2);
                    $wildcardDomainList[$wildcardBase] = $domainName;
                }
            }
        }

        foreach ($subdomains as $subdomain) {
            if ((string) $subdomain->enabled === '1') {
                $subdomainName = (string) $subdomain->FromDomain;
                $isValid = false;
                foreach ($wildcardDomainList as $baseDomain => $wildcardDomain) {
                    if (str_ends_with($subdomainName, $baseDomain)) {
                        $isValid = true;
                        break;
                    }
                }

                if (!$isValid) {
                    $key = $subdomain->__reference; // Dynamic key based on subdomain reference
                    $messages->appendMessage(new Message(
                        sprintf(gettext("Invalid subdomain configuration: '%s' does not fall under any configured wildcard domain."), $subdomainName),
                        $key . ".FromDomain", // Use dynamic key for message referencing
                        "InvalidSubdomain"
                    ));
                }
            }
        }
    }

    // 4. Get the current OPNsense WebGUI ports
    private function getWebGuiPorts() {
        $webgui = Config::getInstance()->object()->system->webgui ?? null;
        $webGuiPorts = [];

        // Only add ports to array if no specific interfaces for the WebGUI are set
        if (!empty($webgui) && empty((string)$webgui->interfaces)) {
            // Add port 443 if no specific port is set, otherwise set custom webgui port
            if (empty($webgui->port)) {
                $webGuiPorts[] = !empty((string)$webgui->port) ? (string)$webgui->port : '443';
            }

            // Add port 80 if HTTP redirect is not explicitly disabled
            if (empty((string)$webgui->disablehttpredirect)) {
                $webGuiPorts[] = '80';
            }
        }

        return $webGuiPorts;
    }

    // 4. Check for conflicts between Caddy and OPNsense WebGUI ports
    private function checkWebGuiSettings($messages) {
        $overlap = array_intersect($this->getWebGuiPorts(), ['80', '443']);
        $tlsAutoHttpsSetting = (string)$this->general->TlsAutoHttps;

        if (!empty($overlap) && $tlsAutoHttpsSetting !== 'off') {
            // Dynamically construct the message based on conflicting ports in $overlap
            $portOverlap = implode(', ', $overlap);
            $portOverlapMessage = [];
            $portOverlapMessageConstruction = implode(' and ', $portOverlapMessage);

            if (in_array('443', $overlap)) {
                $portOverlapMessage[] = gettext('change "TCP port" to a non-standard port, e.g., 8443');
            }
            if (in_array('80', $overlap)) {
                $portOverlapMessage[] = gettext('enable "Disable web GUI redirect rule"');
            }

            $messages->appendMessage(new Message(
                sprintf(gettext('To use Auto HTTPS, resolve these conflicts: Port (%s) are currently configured for the OPNsense WebGUI. Go to "System - Settings - Administration" and %s.'), $portOverlap, $portOverlapMessageConstruction),
                "general.TlsAutoHttps"
            ));
        }
    }

    // Perform the actual validation
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        // 1. Check domain-port combinations
        $this->checkForUniquePortCombos($this->reverseproxy->reverse->iterateItems(), $messages);
        // 2. Check subdomain-port combinations
        $this->checkForUniquePortCombos($this->reverseproxy->subdomain->iterateItems(), $messages);
        // 3. Check that subdomains are under a wildcard or exact domain
        $this->checkSubdomainsAgainstDomains($this->reverseproxy->subdomain->iterateItems(), $this->reverseproxy->reverse->iterateItems(), $messages);
        // 4. Check WebGUI conflicts
        $this->checkWebGuiSettings($messages);

        return $messages;
    }
}
