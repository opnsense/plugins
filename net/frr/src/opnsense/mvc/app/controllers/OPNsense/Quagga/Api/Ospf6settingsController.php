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

use \OPNsense\Quagga\OSPF6;
use \OPNsense\Core\Config;
use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Base\UIModelGrid;

class Ospf6settingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'ospf6';
    protected static $internalModelClass = '\OPNsense\Quagga\OSPF6';
    public function searchInterfaceAction()
    {
        return $this->searchBase('interfaces.interface', array("enabled", "interfacename", "area", "networktype"));
    }
    public function getInterfaceAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('interface', 'interfaces.interface', $uuid);
    }
    public function addInterfaceAction()
    {
        return $this->addBase('interface', 'interfaces.interface');
    }
    public function delInterfaceAction($uuid)
    {
        return $this->delBase('interfaces.interface', $uuid);
    }
    public function setInterfaceAction($uuid)
    {
        return $this->setBase('interface', 'interfaces.interface', $uuid);
    }
    public function toggleInterfaceAction($uuid)
    {
        return $this->toggleBase('interfaces.interface', $uuid);
    }
}
