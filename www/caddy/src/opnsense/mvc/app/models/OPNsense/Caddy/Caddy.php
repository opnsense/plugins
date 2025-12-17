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
    // Check domain-port combinations
    private function checkForUniquePortCombos($messages)
    {
        $combos = [];
        foreach ($this->reverseproxy->reverse->iterateItems() as $item) {
            $key = $item->__reference;
            $fromDomain = (string) $item->FromDomain;
            $fromPort = (string) $item->FromPort;

            if ($fromPort === '') {
                $defaultPorts = ['80', '443'];
            } else {
                $defaultPorts = [$fromPort];
            }

            foreach ($defaultPorts as $port) {
                $comboKey = $fromDomain . ':' . $port;

                if (isset($combos[$comboKey])) {
                    $messages->appendMessage(new Message(
                        sprintf(
                            gettext(
                                'Duplicate entry: The combination of %s and port %s is already used. ' .
                                'Each combination of domain and port must be unique.'
                            ),
                            $fromDomain,
                            $port
                        ),
                        $key . ".FromDomain"
                    ));
                } else {
                    $combos[$comboKey] = true;
                }
            }
        }
    }

    // Prevent the usage of conflicting options when TLS is deactivated for a Domain
    private function checkDisableTlsConflicts($messages)
    {
        foreach ($this->reverseproxy->reverse->iterateItems() as $item) {
            if ($item->isFieldChanged()) {
                if ((string) $item->DisableTls === '1') {
                    $conflictChecks = [
                        'DnsChallenge' => (string) $item->DnsChallenge === '1',
                        'AcmePassthrough' => !empty((string) $item->AcmePassthrough),
                        'CustomCertificate' => !empty((string) $item->CustomCertificate)
                    ];

                    $conflictFields = array_keys(array_filter($conflictChecks));

                    if (!empty($conflictFields)) {
                        $messages->appendMessage(new Message(
                            gettext(
                                'TLS cannot be disabled if one of the following options are used: ' .
                                '"DNS-01 Challenge", "HTTP-01 Challenge Redirection" and "Custom Certificate"'
                            ),
                            $item->__reference . ".DisableTls"
                        ));
                    }
                }
            }
        }
    }

    /**
     * Check that when Superuser is disabled, all ports are 1024 and above.
     * In General settings where this triggers, a validation dialog will show the hidden validation of the domain ports.
     * The default HTTP and HTTPS ports are not allowed to be empty, since then they are 80 and 443.
     * Domain ports are allowed to be empty, since then they have the same value as the HTTP and HTTPS default ports.
     * Any value that is below 1024 will trigger the validation.
     */
    private function checkSuperuserPorts($messages)
    {
        if ((string)$this->general->DisableSuperuser === '1') {
            $httpPort = !empty((string)$this->general->HttpPort) ? (string)$this->general->HttpPort : 80;
            $httpsPort = !empty((string)$this->general->HttpsPort) ? (string)$this->general->HttpsPort : 443;

            // Check default HTTP port
            if ($httpPort < 1024) {
                $messages->appendMessage(new Message(
                    gettext(
                        'www user is active, HTTP port must not be empty and must be 1024 or above.'
                    ),
                    "general.HttpPort"
                ));
            }

            // Check default HTTPS port
            if ($httpsPort < 1024) {
                $messages->appendMessage(new Message(
                    gettext(
                        'www user is active, HTTPS port must not be empty and must be 1024 or above.'
                    ),
                    "general.HttpsPort"
                ));
            }

            // Check ports under domain configurations
            foreach ($this->reverseproxy->reverse->iterateItems() as $item) {
                $fromPort = !empty((string)$item->FromPort) ? (string)$item->FromPort : null;

                if ($fromPort !== null && $fromPort < 1024) {
                    $messages->appendMessage(new Message(
                        gettext(
                            'www user is active, port must be empty or must be 1024 or above.'
                        ),
                        $item->__reference . ".FromPort"
                    ));
                    $messages->appendMessage(new Message(
                        gettext(
                            'Ports in "Reverse Proxy - Domains" must be empty or must be 1024 or above.'
                        ),
                        "general.DisableSuperuser"
                    ));
                }
            }

            foreach ($this->reverseproxy->layer4->iterateItems() as $item) {
                $fromPort = !empty((string)$item->FromPort) ? (string)$item->FromPort : null;

                if ($fromPort !== null && $fromPort < 1024) {
                    $messages->appendMessage(new Message(
                        gettext(
                            'www user is active, port must be empty or must be 1024 or above.'
                        ),
                        $item->__reference . ".FromPort"
                    ));
                    $messages->appendMessage(new Message(
                        gettext(
                            'Ports in "Reverse Proxy - Layer4 Routes" must be empty or must be 1024 or above.'
                        ),
                        "general.DisableSuperuser"
                    ));
                }
            }
        }
    }

    private function checkLayer4Matchers($messages)
    {
        foreach ($this->reverseproxy->layer4->iterateItems() as $item) {
            if ($item->isFieldChanged()) {
                $key = $item->__reference;
                if (
                    in_array((string)$item->Matchers, ['httphost', 'tlssni', 'quicsni']) &&
                    empty((string)$item->FromDomain)
                ) {
                    $messages->appendMessage(new Message(
                        sprintf(
                            gettext(
                                'When "%s" matcher is selected, domain is required.'
                            ),
                            $item->Matchers
                        ),
                        $key . ".FromDomain"
                    ));
                } elseif (
                        !in_array((string)$item->Matchers, ['httphost', 'tlssni', 'quicsni']) &&
                        (
                            !empty((string)$item->FromDomain) &&
                            (string)$item->FromDomain != '*'
                        )
                ) {
                    $messages->appendMessage(new Message(
                        sprintf(
                            gettext(
                                'When "%s" matcher is selected, domain must be empty or *.'
                            ),
                            $item->Matchers
                        ),
                        $key . ".FromDomain"
                    ));
                }

                if (!in_array((string)$item->Matchers, ['tlssni', 'quicsni']) && !empty((string)$item->TerminateTls)) {
                    $messages->appendMessage(new Message(
                        sprintf(
                            gettext(
                                'When "%s" matcher is selected, TLS can not be terminated.'
                            ),
                            $item->Matchers
                        ),
                        $key . ".TerminateTls"
                    ));
                }

                if ((string)$item->Matchers !== 'openvpn' && !empty((string)$item->FromOpenvpnModes)) {
                    $messages->appendMessage(new Message(
                        sprintf(
                            gettext(
                                'When "%s" matcher is selected, field must be empty.'
                            ),
                            $item->Matchers
                        ),
                        $key . ".FromOpenvpnModes"
                    ));
                }

                if ((string)$item->Matchers !== 'openvpn' && !empty((string)$item->FromOpenvpnStaticKey)) {
                    $messages->appendMessage(new Message(
                        sprintf(
                            gettext(
                                'When "%s" matcher is selected, field must be empty.'
                            ),
                            $item->Matchers
                        ),
                        $key . ".FromOpenvpnStaticKey"
                    ));
                }

                if ((string)$item->Type === 'global' && empty((string)$item->FromPort)) {
                    $messages->appendMessage(new Message(
                        sprintf(
                            gettext(
                                'When routing type is "%s", port is required.'
                            ),
                            $item->Type
                        ),
                        $key . ".FromPort"
                    ));
                } elseif ((string)$item->Type !== 'global' && !empty((string)$item->FromPort)) {
                    $messages->appendMessage(new Message(
                        sprintf(
                            gettext(
                                'When routing type is "%s", port must be empty.'
                            ),
                            $item->Type
                        ),
                        $key . ".FromPort"
                    ));
                }

                if ((string)$item->Type !== 'global' && ((string)$item->Protocol !== 'tcp')) {
                    $messages->appendMessage(new Message(
                        sprintf(
                            gettext(
                                'When routing type is "%s", protocol must be TCP.'
                            ),
                            $item->Type
                        ),
                        $key . ".Protocol"
                    ));
                }

                if (
                    (string)$item->Type !== 'global' &&
                    (
                        (string)$item->Matchers == 'tls' ||
                        (string)$item->Matchers == 'http' ||
                        (string)$item->Matchers == 'quic'
                    )
                ) {
                    $messages->appendMessage(new Message(
                        sprintf(
                            gettext(
                                'When routing type is "%s", matchers "HTTP", "TLS" or "QUIC" cannot be chosen.'
                            ),
                            $item->Type
                        ),
                        $key . ".Matchers"
                    ));
                }
            }
        }
    }

    // Perform the actual validation
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        $this->checkForUniquePortCombos($messages);
        $this->checkDisableTlsConflicts($messages);
        $this->checkSuperuserPorts($messages);
        $this->checkLayer4Matchers($messages);

        return $messages;
    }
}
