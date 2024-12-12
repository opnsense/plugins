<?php

/*
 * Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Nrpe\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class CommandController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'command';
    protected static $internalModelClass = '\OPNsense\Nrpe\Command';

    public function searchCommandAction()
    {
        return $this->searchBase('commands.command', ['enabled', 'name', 'nrpecommand', 'arguments']);
    }

    public function getCommandAction($uuid = null)
    {
        return $this->getBase('command', 'commands.command', $uuid);
    }

    public function addCommandAction()
    {
        return $this->addBase('command', 'commands.command');
    }

    public function delCommandAction($uuid)
    {
        return $this->delBase('commands.command', $uuid);
    }

    public function setCommandAction($uuid)
    {
        return $this->setBase('command', 'commands.command', $uuid);
    }

    public function toggleCommandAction($uuid)
    {
        return $this->toggleBase('commands.command', $uuid);
    }
}
