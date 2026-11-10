<?php

/*
 * Copyright (C) 2026 Pat Ryan
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
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

namespace OPNsense\MDNSBridge;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

class MDNSBridge extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        if ((string)$this->enabled === '1') {
            $ifaces = array_filter(explode(',', (string)$this->interfaces));
            if (count($ifaces) < 2) {
                $messages->appendMessage(new Message(
                    gettext('Select at least two interfaces.'),
                    $this->interfaces->__reference
                ));
            }
            if ((string)$this->disableipv4 === '1' && (string)$this->disableipv6 === '1') {
                $messages->appendMessage(new Message(
                    gettext('IPv4 and IPv6 cannot both be disabled.'),
                    $this->disableipv6->__reference
                ));
            }
        }

        return $messages;
    }
}
