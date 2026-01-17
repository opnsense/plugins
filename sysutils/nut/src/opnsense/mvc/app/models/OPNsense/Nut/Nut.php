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

    public function isServer()
    {
        return $this->general->mode == "standalone" ||
            $this->general->mode == "netserver";
    }

    // A listen address is required if not in netclient mode.
    private function checkListenAddressForServer($messages)
    {
        if ($this->isServer() && empty($this->data_server->listen)) {
            $messages->appendMessage(new Message(
                gettext(
                    "Address required: A listen address is required if in " .
                    "standalone or netserver mode."
                ),
                "data_server.listen"
            ));
        }
    }

    // Assumes that only 127.0.0.1 and ::1 are valid loopback addresses.
    //
    // Techinically all of 127.0.0.0/8 could be set up as loopback addresses,
    // but by default opnSense/FreeBSD doesn't configure these.
    public function getLoopbackListenAddress()
    {
        if ($this->cachedLoopbackListenAddress === null) {
            $host = "";
            foreach ($this->data_server->listen->getValues() as $address) {
                $parts = explode(":", $address);
                if (
                    Util::isIpv4Address($parts[0]) &&
                    Util::isIPInCIDR($parts[0], "127.0.0.1/32")
                ) {
                    $host = $address;
                }
                $parts = preg_split(
                    "/\[([^\]]+)\]/",
                    $address,
                    -1,
                    PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
                );
                if (
                    Util::isIpv6Address($parts[0]) &&
                    Util::isIPInCIDR($parts[0], "::1/128")
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
            $this->isServer() &&
            !empty($this->monitoring->local->getNodes()) &&
            empty($this->getLoopbackListenAddress())
        ) {
            if ($this->data_server->listen->isFieldChanged()) {
                $messages->appendMessage(new Message(
                    gettext(
                        "Loopback required: A loopback listen address is " .
                        "required when local monitors are defined. Add a " .
                        "listen address using 127.0.0.1 or ::1."
                    ),
                    "data_server.listen"
                ));
            }
            foreach ($this->monitoring->local->iterateItems() as $monitor) {
                if ($monitor->isFieldChanged()) {
                    $messages->appendMessage(new Message(
                        gettext(
                            "Loopback required: A loopback listen address is " .
                            "required when local monitors are defined. Add a " .
                            "listen address using 127.0.0.1 or ::1."
                        ),
                        $monitor->__reference . ".enabled"
                    ));
                }
            }
        }
    }

    // Ensure usernames are unique.
    private function checkForUniqueUsernames($messages)
    {
        $users = [];
        foreach ($this->user->iterateItems() as $user) {
            $username = (string) $user->username;
            if (isset($users[$username])) {
                $messages->appendMessage(new Message(
                    sprintf(
                        gettext(
                            "Duplicate entry: The username %s is already " .
                            "used. Each username must be unique."
                        ),
                        $username
                    ),
                    $user->__reference . ".username"
                ));
            } else {
                $users[$username] = true;
            }
        }
    }

    // Ensure UPS names are unique.
    private function checkForUniqueUpsNames($messages)
    {
        $upss = [];
        foreach ($this->drivers->ups->iterateItems() as $ups) {
            $name = (string) $ups->name;
            if (isset($upss[$name])) {
                $messages->appendMessage(new Message(
                    sprintf(
                        gettext(
                            "Duplicate entry: The name %s is already used. " .
                            "Each name must be unique."
                        ),
                        $name
                    ),
                    $ups->__reference . ".name"
                ));
            } else {
                $upss[$name] = true;
            }
        }
    }

    // Ensure each monitor points to a unique UPS.
    private function checkForUniqueUpsHostnameCombos($messages)
    {
        $monitors = [];
        foreach ($this->monitoring->local->iterateItems() as $monitor) {
            $upsUuid = (string) $monitor->ups;
            if (isset($monitors[$upsUuid])) {
                $ups = $this->getNodeByReference("drivers.ups." . $upsUuid);
                $messages->appendMessage(new Message(
                    sprintf(
                        gettext(
                            "Duplicate entry: A monitor for the local UPS %s " .
                            "is already defined. Each local UPS may only " .
                            "have one monitor."
                        ),
                        $ups->name
                    ),
                    $monitor->__reference . ".ups"
                ));
            } else {
                $monitors[$upsUuid] = true;
            }
        }
        foreach ($this->monitoring->remote->iterateItems() as $monitor) {
            $upsName = (string) $monitor->ups_name;
            $hostname = (string) $monitor->hostname;
            $key = $upsName . "@" . $hostname;
            if (isset($monitors[$key])) {
                $messages->appendMessage(new Message(
                    sprintf(
                        gettext(
                            "Duplicate entry: A monitor for UPS %s at %s is " .
                            "already defined. Each combination of UPS name " .
                            "and hostname must be unique."
                        ),
                        $upsName,
                        $hostname
                    ),
                    $monitor->__reference . ".ups_name"
                ));
            } else {
                $monitors[$key] = true;
            }
        }
    }

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        // Invalidate the cached loopback listen address if the listen addresses
        // were changed.
        if ($this->data_server->listen->isFieldChanged()) {
            $this->cachedLoopbackListenAddress = null;
        }

        $this->checkListenAddressForServer($messages);
        $this->checkListenAddressForLocalMonitors($messages);
        $this->checkForUniqueUsernames($messages);
        $this->checkForUniqueUpsNames($messages);
        $this->checkForUniqueUpsHostnameCombos($messages);

        return $messages;
    }
}
