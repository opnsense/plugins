<?php

/**
 *    Copyright (C) 2026 Gabriel Smith <ga29smith@gmail.com>
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

namespace OPNsense\Nut\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class UsersController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\Nut\Nut';
    protected static $internalModelName = 'nut';

    public function searchUserAction()
    {
        return $this->searchBase("user", null, "username");
    }

    public function getUserAction($uuid = null)
    {
        return $this->getBase("user", "user", $uuid);
    }

    public function addUserAction()
    {
        return $this->addBase("user", "user");
    }

    public function setUserAction($uuid)
    {
        return $this->setBase("user", "user", $uuid);
    }

    public function delUserAction($uuid)
    {
        return $this->delBase("user", $uuid);
    }

    public function toggleUserAction($uuid, $enabled = null)
    {
        return $this->toggleBase("user", $uuid, $enabled);
    }
}
