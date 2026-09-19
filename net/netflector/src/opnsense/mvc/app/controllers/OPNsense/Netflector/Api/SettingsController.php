<?php

/*
 * Copyright (C) 2026 Sergii Bogomolov
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Netflector\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'netflector';
    protected static $internalModelClass = '\OPNsense\Netflector\Netflector';

    public function searchReflectorAction()
    {
        return $this->searchBase(
            'reflectors.reflector',
            [
                'enabled', 'name', 'source_if', 'target_if', 'description',
                'wol', 'mdns', 'ssdp', 'dial', 'wsd', 'address_family',
            ]
        );
    }

    public function getReflectorAction($uuid = null)
    {
        return $this->getBase('reflector', 'reflectors.reflector', $uuid);
    }

    public function addReflectorAction()
    {
        return $this->addBase('reflector', 'reflectors.reflector');
    }

    public function setReflectorAction($uuid)
    {
        return $this->setBase('reflector', 'reflectors.reflector', $uuid);
    }

    public function delReflectorAction($uuid)
    {
        return $this->delBase('reflectors.reflector', $uuid);
    }

    public function toggleReflectorAction($uuids, $enabled = null)
    {
        return $this->toggleBase('reflectors.reflector', $uuids, $enabled);
    }
}
