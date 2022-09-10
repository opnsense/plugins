<?php

/**
 *    Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
 *    Copyright (C) 2019 Deciso B.V.
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

namespace OPNsense\Bind\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class DomainController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'domain';
    protected static $internalModelClass = '\OPNsense\Bind\Domain';

    public function searchMasterDomainAction()
    {
        return $this->searchBase(
            'domains.domain',
            [   "enabled", "type", "domainname", "ttl", "refresh", "retry", "expire", "negative" ],
            "domainname",
            function ($record) {
                return $record->type->getNodeData()["master"]["selected"] === 1;
            }
        );
    }

    public function searchSlaveDomainAction()
    {
        return $this->searchBase(
            'domains.domain',
            [   "enabled", "type", "domainname", "masterip" ],
            "domainname",
            function ($record) {
                return $record->type->getNodeData()["slave"]["selected"] === 1;
            }
        );
    }

    public function getDomainAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('domain', 'domains.domain', $uuid);
    }

    public function addMasterDomainAction($uuid = null)
    {
        return $this->addBase('domain', 'domains.domain', ['type' => 'master']);
    }

    public function addSlaveDomainAction($uuid = null)
    {
        return $this->addBase('domain', 'domains.domain', ['type' => 'slave']);
    }

    public function delDomainAction($uuid)
    {
        return $this->delBase('domains.domain', $uuid);
    }

    public function setDomainAction($uuid = null)
    {
        return $this->setBase('domain', 'domains.domain', $uuid);
    }

    public function toggleDomainAction($uuid)
    {
        return $this->toggleBase('domains.domain', $uuid);
    }
}
