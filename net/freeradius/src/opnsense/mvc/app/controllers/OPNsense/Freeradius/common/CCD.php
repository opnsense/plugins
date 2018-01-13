<?php

/*
 * Copyright (C) 2018 EugenMayer
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

namespace OPNsense\Freeradius\common;


class CCD
{
    public $common_name;
    // CIDR
    public $tunnel_network;
    // CIDR
    public $tunnel_networkv6;
    // CIDR
    public $local_network;
    // CIDR
    public $local_networkv6;
    // CIDR
    public $remote_network;
    // CIDR
    public $remote_network6;
    // redirect gateway
    public $gwredir;
    /**
     * if not empty, push will be reset. We aren`t using a boolean due to the legacy code
     */
    public $push_reset;
    /**
     * if not empty, client will be blocked. We aren`t using a boolean due to the legacy code
     */
    public $block = NULL;


    /**
     * @return CCD
     */
    static public function fromFreeradiusUsers($user)
    {
        $ccd = new CCD();
        $ccd->common_name = $user->username->__toString();

        if (isset($user->ip) && isset($user->subnet)) {
            $ip = $user->ip->__toString();
            $prefix = self::netmaskToCidrPrefix($user->subnet->__toString());
            $ccd->tunnel_network = "$ip/$prefix";
        }
        return $ccd;
    }

    /**
     * @param string $netmask netmask as 255.255.255.0
     * @return int prefix like 24 for the above
     */
    static public function netmaskToCidrPrefix($netmask) {
        $long = ip2long($netmask);
        $base = ip2long('255.255.255.255');
        return 32-log(($long ^ $base)+1,2);
    }

}