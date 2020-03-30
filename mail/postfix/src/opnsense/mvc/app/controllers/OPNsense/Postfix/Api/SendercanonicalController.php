<?php

/*
 * Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
 * Copyright (C) 2019 Felix Matouschek <felix@matouschek.org>
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

class SendercanonicalController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'sendercanonical';
    protected static $internalModelClass = '\OPNsense\Postfix\Sendercanonical';

    public function searchSendercanonicalAction()
    {
        return $this->searchBase('sendercanonicals.sendercanonical', array("enabled", "from", "to"));
    }

    public function getSendercanonicalAction($uuid = null)
    {
        return $this->getBase('sendercanonical', 'sendercanonicals.sendercanonical', $uuid);
    }

    public function addSendercanonicalAction()
    {
        return $this->addBase('sendercanonical', 'sendercanonicals.sendercanonical');
    }

    public function delSendercanonicalAction($uuid)
    {
        return $this->delBase('sendercanonicals.sendercanonical', $uuid);
    }

    public function setSendercanonicalAction($uuid)
    {
        return $this->setBase('sendercanonical', 'sendercanonicals.sendercanonical', $uuid);
    }

    public function toggleSendercanonicalAction($uuid)
    {
        return $this->toggleBase('sendercanonicals.sendercanonical', $uuid);
    }
}
