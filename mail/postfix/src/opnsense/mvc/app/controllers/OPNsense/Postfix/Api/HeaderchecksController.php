<?php

/*
 * Copyright (C) 2020 Starkstromkonsument
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

namespace OPNsense\Postfix\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class HeaderchecksController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'headerchecks';
    protected static $internalModelClass = '\OPNsense\Postfix\Headerchecks';

    public function searchHeaderchecksAction()
    {
        return $this->searchBase('headerchecks.headercheck', array("enabled", "expression", "filter"));
    }

    public function getHeadercheckAction($uuid = null)
    {
        return $this->getBase('headercheck', 'headerchecks.headercheck', $uuid);
    }

    public function addHeadercheckAction()
    {
        return $this->addBase('headercheck', 'headerchecks.headercheck');
    }

    public function delHeadercheckAction($uuid)
    {
        return $this->delBase('headerchecks.headercheck', $uuid);
    }

    public function setHeadercheckAction($uuid)
    {
        return $this->setBase('headercheck', 'headerchecks.headercheck', $uuid);
    }

    public function toggleHeadercheckAction($uuid)
    {
        return $this->toggleBase('headerchecks.headercheck', $uuid);
    }
}
