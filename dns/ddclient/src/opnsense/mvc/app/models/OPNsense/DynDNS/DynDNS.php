<?php

/*
 * Copyright (C) 2021-2023 Deciso B.V.
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

 namespace OPNsense\DynDNS;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

/**
 * Class DynDNS
 * @package OPNsense\DynDNS
 */
class DynDNS extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        $validate_servers = [];
        foreach ($this->getFlatNodes() as $key => $node) {
            $tagName = $node->getInternalXMLTagName();
            $parentNode = $node->getParentNode();
            if ($validateFullModel || $node->isFieldChanged()) {
                if ($parentNode->getInternalXMLTagName() === 'account' && in_array($tagName, ['protocol', 'server'])) {
                    $parentKey = $parentNode->__reference;
                    $validate_servers[$parentKey] = $parentNode;
                }
            }
        }
        foreach ($validate_servers as $key => $node) {
            if ((string)$node->service != 'custom') {
                continue;
            }
            $srv = (string)$node->server;
            if (in_array((string)$node->protocol, ['get', 'post', 'put'])) {
                if (empty($srv) || filter_var($srv, FILTER_VALIDATE_URL) === false) {
                    $messages->appendMessage(
                        new Message(
                            gettext("A valid URI is required."),
                            $key . ".server"
                        )
                    );
                }
            } else {
                if (empty($srv) || filter_var($srv, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                    $messages->appendMessage(
                        new Message(
                            gettext("A valid domain is required."),
                            $key . ".server"
                        )
                    );
                }
            }
        }
        return $messages;
    }
}
