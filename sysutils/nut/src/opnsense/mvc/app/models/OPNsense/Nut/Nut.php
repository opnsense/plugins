<?php

/*
    Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
    Copyright (C) 2026 Gabriel Smith <ga29smith@gmail.com>
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

namespace OPNsense\Nut;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Firewall\Util;

class Nut extends BaseModel
{
    /**
     * @var null|string the cached listen address for loopback connections if one exists
     */
    private $cachedLoopbackListenAddress = null;

    // Assumes that only 127.0.0.1 and ::1 are valid loopback addresses.
    //
    // Techinically all of 127.0.0.0/8 could be set up as loopback addresses,
    // but by default opnSense/FreeBSD doesn't configure these.
    public function getLoopbackListenAddress()
    {
        if ($this->cachedLoopbackListenAddress === null) {
            $host = "";
            foreach ($this->general->listen->getValues() as $address) {
                if (
                    Util::isIpv4Address($address) &&
                    Util::isIPInCIDR($address, "127.0.0.1/32")
                ) {
                    $host = $address;
                } elseif (
                    Util::isIpv6Address($address) &&
                    Util::isIPInCIDR($address, "::1/128")
                ) {
                    $host = $address;
                }
            }
            $this->cachedLoopbackListenAddress = $host;
        }
        return $this->cachedLoopbackListenAddress;
    }

    // If any local monitors are defined, some sort of loopback must be defined
    // in the listen field.
    private function checkListenAddressForLocalMonitors($messages)
    {
        if (
            $this->general->mode == "standalone" &&
            !empty($this->drivers->ups->getNodes()) &&
            empty($this->getLoopbackListenAddress())
        ) {
            if ($this->general->listen->isFieldChanged()) {
                $messages->appendMessage(new Message(
                    gettext(
                        "Loopback required: A loopback listen address is "
                        . "required when a local UPS is defined. Add a "
                        . "listen address using 127.0.0.1 or ::1."
                    ),
                    "general.listen"
                ));
            }
            foreach ($this->drivers->ups->iterateItems() as $ups) {
                if ($ups->isFieldChanged()) {
                    $messages->appendMessage(new Message(
                        gettext(
                            "Loopback required: A loopback listen address is "
                            . "required when local a UPS is defined. Add a "
                            . "listen address using 127.0.0.1 or ::1."
                        ),
                        $ups->__reference . ".enabled"
                    ));
                }
            }
        }
    }

    private function checkForUniqueUpsDefinitions($messages)
    {
        // Ensure UPS names are unique.
        $ups_names = [];
        foreach ($this->drivers->ups->iterateItems() as $ups) {
            $name = (string) $ups->name;
            if (isset($ups_names[$name])) {
                $messages->appendMessage(new Message(
                    sprintf(
                        gettext(
                            "Duplicate entry: The name %s is already used. "
                            . "Each name must be unique."
                        ),
                        $name
                    ),
                    $ups->__reference . ".name"
                ));
            } else {
                $ups_names[$name] = true;
            }
        }

        // Ensure each UPS driver/port combination is unique.
        $ups_ports = [];
        foreach ($this->drivers->ups->iterateItems() as $ups) {
            $driver = (string) $ups->driver;
            $port = (string) $ups->port;
            $key = $driver . "_" . $port;
            if (isset($ups_ports[$key])) {
                $messages->appendMessage(new Message(
                    sprintf(
                        gettext(
                            "Duplicate entry: A UPS with driver %s and port "
                            . "%s is already defined. Each driver and port "
                            . "combination must be unique."
                        ),
                        $name
                    ),
                    $ups->__reference . ".port"
                ));
            } else {
                $ups_ports[$key] = true;
            }
        }
    }

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        // Invalidate the cached loopback listen address if the listen addresses
        // were changed.
        if ($this->general->listen->isFieldChanged()) {
            $this->cachedLoopbackListenAddress = null;
        }

        $this->checkForUniqueUpsDefinitions($messages);
        $this->checkListenAddressForLocalMonitors($messages);

        return $messages;
    }
}
