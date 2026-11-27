<?php

/*
 * Copyright (C) 2026 Richard Aspden <rick+github@insanityinside.net>
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

namespace OPNsense\Cloudflared;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

class Cloudflared extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        if (
            ($validateFullModel || $this->general->enabled->isFieldChanged() || $this->general->token->isFieldChanged()) &&
            !$this->general->enabled->isEmpty() && $this->general->token->isEmpty()
        ) {
            $messages->appendMessage(new Message(
                gettext('A tunnel token is required when the service is enabled.'),
                $this->general->token->__reference
            ));
        }

        if (
            ($validateFullModel || $this->general->post_quantum->isFieldChanged() || $this->general->protocol->isFieldChanged()) &&
            !$this->general->post_quantum->isEmpty() && (string)$this->general->protocol == 'http2'
        ) {
            $messages->appendMessage(new Message(
                gettext('Post-quantum encryption requires the QUIC protocol.'),
                $this->general->protocol->__reference
            ));
        }

        return $messages;
    }
}
