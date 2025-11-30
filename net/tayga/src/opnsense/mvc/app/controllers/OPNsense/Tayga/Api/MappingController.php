<?php

/*
    Copyright (C) 2025 Matthias Valvekens <dev@mvalvekens.be>
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

namespace OPNsense\Tayga\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class MappingController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'staticmapping';
    protected static $internalModelClass = '\OPNsense\Tayga\StaticMapping';

    public function searchStaticmappingAction()
    {
        return $this->searchBase('staticmappings.staticmapping', ['enabled', 'v4', 'v6']);
    }

    public function getStaticmappingAction($uuid = null)
    {
        return $this->getBase('staticmapping', 'staticmappings.staticmapping', $uuid);
    }

    public function addStaticmappingAction()
    {
        return $this->addBase('staticmapping', 'staticmappings.staticmapping');
    }

    public function delStaticmappingAction($uuid)
    {
        return $this->delBase('staticmappings.staticmapping', $uuid);
    }

    public function setStaticmappingAction($uuid)
    {
        return $this->setBase('staticmapping', 'staticmappings.staticmapping', $uuid);
    }

    public function toggleStaticmappingAction($uuid)
    {
        return $this->toggleBase('staticmappings.staticmapping', $uuid);
    }
}
