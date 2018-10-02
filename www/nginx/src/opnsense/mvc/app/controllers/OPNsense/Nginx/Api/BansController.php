<?php
/*

    Copyright (C) 2018 Fabian Franz
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/
namespace OPNsense\Nginx\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;

class BansController extends ApiMutableModelControllerBase
{
    static protected $internalModelClass = '\OPNsense\Nginx\Nginx';
    static protected $internalModelName = 'nginx';
    public function searchbanAction()
    {
        return $this->searchBase('ban', array('ip', 'time'));
    }
    public function delbanAction($uuid)
    {
        if ($this->request->isPost() || $this->request->isDelete()) {
            $mdl = $this->getModel();
            $node = $mdl->getNodeByReference('ban.' . $uuid);
            $backend = new Backend();
            $backend->configdRun('nginx unlock ' . (string)$node->ip);
            return $this->delBase('ban', $uuid);
        } else {
            return array('result' => 'most be called via POST');
        }
    }
}
