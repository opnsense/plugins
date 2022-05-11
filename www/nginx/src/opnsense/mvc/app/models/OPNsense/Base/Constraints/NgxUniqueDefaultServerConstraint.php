<?php

/*
 * Copyright (C) 2021 Markus Peter <mpeter@one-it.de>
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

namespace OPNsense\Base\Constraints;

use Phalcon\Messages\Message;

/**
 * a very specific nginx check - not reusable
 * it checks for the uniqueness of servers with the default_server directive
 *
 * Class NgxUniqueDefaultServerConstraint
 * @package OPNsense\Nginx\Constraints
 */
class NgxUniqueDefaultServerConstraint extends BaseConstraint
{
    public function validate($validator, $attribute): bool
    {
        $node = $this->getOption('node');
        if ($node) {
            $httpServerNode = $node->getParentNode();
            $defaultServerNode = $httpServerNode->getChild("default_server");
            if (!$this->isEmpty($defaultServerNode)) {
                $myUUID = $httpServerNode->getAttribute("uuid");
                $myListenHTTPAddress = $httpServerNode->getChild("listen_http_address");

                $httpServersNode = $httpServerNode->getParentNode();

                $httpServers = $httpServersNode->getChildren();

                $msg = "";
                foreach ($httpServers as $httpServer) {
                    $uuid = $httpServer->getAttribute("uuid");
                    if ($uuid != $myUUID) {
                        $defaultServerNode = $httpServer->getChild("default_server");
                        if (!$this->isEmpty($defaultServerNode)) {
                            $listenHTTPAddressNode = $httpServer->getChild("listen_http_address");
                            if ($this->compareListenAddresses($myListenHTTPAddress, $listenHTTPAddressNode, $msg)) {
                                $validator->appendMessage(new Message(
                                    sprintf(gettext("There can only be one Default Server on each listening address: %s conflict."), $msg),
                                    $attribute
                                ));
                            }
                        }
                    }
                }
            }
        }
        return true;
    }

    private function compareListenAddresses($as, $bs, &$msg): bool
    {
        foreach (explode(",", $as) as $a) {
            list($a_af, $a_ip, $a_port) = $this->extractAFIPPort($a);
            foreach (explode(",", $bs) as $b) {
                list($b_af, $b_ip, $b_port) = $this->extractAFIPPort($b);
                if ($a_af == $b_af && $a_port == $b_port) {
                    if ($a_ip == null || $a_ip == "::" || $b_ip == null || $b_ip == "::" || $a_ip == $b_ip) {
                        $msg = "IPv" . $a_af . ": [" . $a_ip . "]:" . $a_port . " and IPv" . $b_af . ": [" . $b_ip . "]:" . $b_port;
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function extractAFIPPort($in): array
    {
        $af = null;
        $ip = null;
        $port = null;
        if (!strpos($in, ":")) {
            //if only number, then ipv4 port only
            $af = 4;
            $port = $in;
        } else {
            //extract ip and port
            if (preg_match("/(?:([0-9.]+)|\[([0-9a-fA-F:]+)\]):(\d+)/", $in, $parts)) {
            }
            {
            if (strpos($in, "[") === 0) {
                $af = 6;
                $ip = inet_ntop(inet_pton($parts[2]));
                $port = $parts[3];
            } else {
                $af = 4;
                $ip = long2ip(ip2long($parts[1]));
                $port = $parts[3];
            }
            }
        }
        return [$af, $ip, $port];
    }
}
