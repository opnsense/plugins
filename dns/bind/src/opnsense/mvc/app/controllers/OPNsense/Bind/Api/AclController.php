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

namespace OPNsense\Bind\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class AclController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'acl';
    protected static $internalModelClass = '\OPNsense\Bind\Acl';

    public function searchAclAction()
    {
        return $this->searchBase('acls.acl', array("enabled", "name", "networks"));
    }
    public function getAclAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('acl', 'acls.acl', $uuid);
    }
    public function addAclAction()
    {
        return $this->addBase('acl', 'acls.acl');
    }
    public function delAclAction($uuid)
    {
        return $this->delBase('acls.acl', $uuid);
    }
    public function setAclAction($uuid)
    {
        return $this->setBase('acl', 'acls.acl', $uuid);
    }
    public function toggleAclAction($uuid)
    {
        return $this->toggleBase('acls.acl', $uuid);
    }
}
