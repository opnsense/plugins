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

class CloakController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'cloak';
    protected static $internalModelClass = '\OPNsense\Dnscryptproxy\Cloak';

    public function searchCloakAction()
    {
        return $this->searchBase('cloaks.cloak', array("enabled", "name", "destination"));
    }
    public function getCloakAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('cloak', 'cloaks.cloak', $uuid);
    }
    public function addCloakAction()
    {
        return $this->addBase('cloak', 'cloaks.cloak');
    }
    public function delCloakAction($uuid)
    {
        return $this->delBase('cloaks.cloak', $uuid);
    }
    public function setCloakAction($uuid)
    {
        return $this->setBase('cloak', 'cloaks.cloak', $uuid);
    }
    public function toggleCloakAction($uuid)
    {
        return $this->toggleBase('cloaks.cloak', $uuid);
    }
}
