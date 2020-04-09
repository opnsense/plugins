<?php

/*
 *    Copyright (C) 2015-2017 Deciso B.V.
 *    Copyright (C) 2015 Jos Schellevis
 *    Copyright (C) 2017 Fabian Franz
 *    Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
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
 */

namespace OPNsense\Quagga\Api;

use OPNsense\Quagga\Staticd;
use OPNsense\Core\Config;
use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;

class StaticdsettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'staticd';
    protected static $internalModelClass = '\OPNsense\Quagga\Staticd';
    public function searchStaticdv4Action()
    {
        return $this->searchBase('networks.networkv4', array("enabled", "ipaddr", "netmask", "gateway", "interfacename", "distance", "blackhole"));
    }
    public function searchStaticdv6Action()
    {
        return $this->searchBase('networks.networkv6', array("enabled", "ipaddr", "netmask", "gateway", "interfacename", "distance", "blackhole"));
    }    
    public function getStaticdv4Action($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('networkv4', 'networks.networkv4', $uuid);
    }
    public function getStaticdv6Action($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('networkv6', 'networks.networkv6', $uuid);
    }
    public function addStaticdv4Action()
    {
        return $this->addBase('networkv4', 'networks.networkv4');
    }
    public function addStaticdv6Action()
    {
        return $this->addBase('networkv6', 'networks.networkv6');
    }    
    public function delStaticdv4Action($uuid)
    {
        return $this->delBase('networks.networkv4', $uuid);
    }
    public function delStaticdv6Action($uuid)
    {
        return $this->delBase('networks.networkv6', $uuid);
    }    
    public function setStaticdv4Action($uuid)
    {
        return $this->setBase('networkv4', 'networks.networkv4', $uuid);
    }
    public function setStaticdv6Action($uuid)
    {
        return $this->setBase('networkv6', 'networks.networkv6', $uuid);
    }    
    public function toggleStaticdv4Action($uuid)
    {
        return $this->toggleBase('networks.networkv4', $uuid);
    }
    public function toggleStaticdv6Action($uuid)
    {
        return $this->toggleBase('networks.networkv6', $uuid);
    }    
}

