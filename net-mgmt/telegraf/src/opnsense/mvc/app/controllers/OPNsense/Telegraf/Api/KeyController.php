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

namespace OPNsense\Telegraf\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class KeyController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'key';
    protected static $internalModelClass = '\OPNsense\Telegraf\Key';

    public function searchKeyAction()
    {
        return $this->searchBase('keys.key', array("enabled", "name", "value"));
    }
    public function getKeyAction($uuid = null)
    {
        return $this->getBase('key', 'keys.key', $uuid);
    }
    public function addKeyAction()
    {
        return $this->addBase('key', 'keys.key');
    }
    public function delKeyAction($uuid)
    {
        return $this->delBase('keys.key', $uuid);
    }
    public function setKeyAction($uuid)
    {
        return $this->setBase('key', 'keys.key', $uuid);
    }
    public function toggleKeyAction($uuid)
    {
        return $this->toggleBase('keys.key', $uuid);
    }
}
