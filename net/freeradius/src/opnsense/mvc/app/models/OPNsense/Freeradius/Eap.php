<?php

namespace OPNsense\Freeradius;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

/*
    Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
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

class Eap extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        if (
            $validateFullModel ||
            $this->tls_min_version->isFieldChanged() ||
            $this->tls_max_version->isFieldChanged()
        ) {
            if ($this->tls_min_version->asFloat() > $this->tls_max_version->asFloat()) {
                $messages->appendMessage(new Message(
                    gettext('TLS minimum version must be less than or equal to TLS maximum version.'),
                    $this->tls_max_version->getInternalXMLTagName()
                ));
            }
        }

        if ((string)$this->restrict_outer_eap_methods === '1') {
            $allowed = explode(',', (string)$this->allowed_outer_eap_types);
            if ((string)$this->allowed_outer_eap_types === '') {
                $messages->appendMessage(new Message(
                    gettext('Select at least one allowed outer EAP method.'),
                    $this->allowed_outer_eap_types->getInternalXMLTagName()
                ));
            } elseif (
                count($allowed) !== count(array_unique($allowed)) ||
                array_diff($allowed, ['tls', 'ttls', 'peap', 'pwd', 'mschapv2', 'md5'])
            ) {
                $messages->appendMessage(new Message(
                    gettext('Select at least one recognized EAP method without duplicates.'),
                    $this->allowed_outer_eap_types->getInternalXMLTagName()
                ));
            }
            // Handler availability is independent of outer permission; do not change enable_pwd here.
            if (in_array('pwd', $allowed, true) && (string)$this->enable_pwd !== '1') {
                $messages->appendMessage(new Message(
                    gettext('Enable EAP-PWD before including it in Allowed Outer EAP Methods.'),
                    $this->enable_pwd->getInternalXMLTagName()
                ));
            }
            if (!in_array((string)$this->default_eap_type, $allowed, true)) {
                $messages->appendMessage(new Message(
                    gettext('The Default EAP Type must be included in Allowed Outer EAP Methods.'),
                    $this->default_eap_type->getInternalXMLTagName()
                ));
            }
        }

        return $messages;
    }
}
