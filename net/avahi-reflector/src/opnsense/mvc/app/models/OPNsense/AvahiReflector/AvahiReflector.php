<?php

/*
 * Copyright (C) 2024 OPNsense Community
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

namespace OPNsense\AvahiReflector;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

class AvahiReflector extends BaseModel
{
    /**
     * The avahi-daemon INI parser uses a fixed-size line buffer (256 bytes on
     * FreeBSD).  Lines that exceed this limit are silently truncated, causing
     * the remainder to be parsed as a separate (malformed) line and the daemon
     * to refuse to start.  The config line is:
     *
     *   reflect-filters=<value>\n
     *
     * "reflect-filters=" is 16 characters, leaving 239 for the value itself
     * (256 - 16 - 1 for the trailing newline).
     */
    private const REFLECT_FILTERS_MAX_LENGTH = 239;

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        $filters = (string)$this->reflect_filters;
        if (strlen($filters) > self::REFLECT_FILTERS_MAX_LENGTH) {
            $messages->appendMessage(new Message(
                sprintf(
                    'Reflect filters exceed the %d-character limit imposed by the '
                    . 'avahi-daemon config parser (currently %d). Remove entries or '
                    . 'drop the ".local" suffix — Avahi uses substring matching so '
                    . 'the suffix is not required.',
                    self::REFLECT_FILTERS_MAX_LENGTH,
                    strlen($filters)
                ),
                $this->reflect_filters->getInternalXMLTagName()
            ));
        }

        return $messages;
    }
}
