<?php

/*
 * Copyright (C) 2017 Michael Muenz
 * Copyright (C) 2018 Fabian Franz
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

use \OPNsense\Base\ApiMutableModelControllerBase;

class RecipientController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'recipient';
    static protected $internalModelClass = '\OPNsense\Postfix\Recipient';

    public function searchRecipientAction()
    {
        return $this->searchBase('recipients.recipient', array("enabled", "address", "action"));
    }

    public function getRecipientAction($uuid = null)
    {
        return $this->getBase('recipient', 'recipients.recipient', $uuid);
    }

    public function addRecipientAction()
    {
        return $this->addBase('recipient', 'recipients.recipient');
    }

    public function delRecipientAction($uuid)
    {
        return $this->delBase('recipients.recipient', $uuid);
    }

    public function setRecipientAction($uuid)
    {
        return $this->setBase('recipient', 'recipients.recipient', $uuid);
    }

    public function toggleRecipientAction($uuid)
    {
        return $this->toggleBase('recipients.recipient', $uuid);
    }
}
