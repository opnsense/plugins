<?php

/*
 * Copyright (C) 2026 Farzad Sadeghi <devi+github@terminaldweller.com>
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

namespace OPNsense\Yggdrasil;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

class Yggdrasil extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        if ((string)$this->general->IfNameMode === 'custom') {
            $name = (string)$this->general->IfNameCustom;

            if (!preg_match('/\A[a-zA-Z0-9_.-]+\z/', $name)) {
                $messages->appendMessage(new Message(
                    gettext('Enter an interface name containing only letters, digits, underscores, dots or hyphens.'),
                    'general.IfNameCustom'
                ));
            }
        }

        $nodeInfo = (string)$this->general->NodeInfo;

        if ($nodeInfo !== '') {
            $decoded = json_decode($nodeInfo);

            if (json_last_error() !== JSON_ERROR_NONE || !is_object($decoded)) {
                $messages->appendMessage(new Message(
                    gettext('Node information must be a valid JSON object.'),
                    'general.NodeInfo'
                ));
            }
        }

        return $messages;
    }
}
