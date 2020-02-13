<?php

/**
 *    Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Dnscryptproxy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class ForwardController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'forward';
    protected static $internalModelClass = '\OPNsense\Dnscryptproxy\Forward';

    public function searchForwardAction()
    {
        return $this->searchBase('forwards.forward', array("enabled", "domain", "dnsserver"));
    }
    public function getForwardAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('forward', 'forwards.forward', $uuid);
    }
    public function addForwardAction()
    {
        return $this->addBase('forward', 'forwards.forward');
    }
    public function delForwardAction($uuid)
    {
        return $this->delBase('forwards.forward', $uuid);
    }
    public function setForwardAction($uuid)
    {
        return $this->setBase('forward', 'forwards.forward', $uuid);
    }
    public function toggleForwardAction($uuid)
    {
        return $this->toggleBase('forwards.forward', $uuid);
    }
}
