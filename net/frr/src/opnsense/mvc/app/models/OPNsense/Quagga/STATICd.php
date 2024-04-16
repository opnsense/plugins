<?php

/**
 *    Copyright (C) 2024 Deciso B.V.
 *    Copyright (C) 2024 Mike Shuey
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Quagga;

use OPNsense\Base\Messages\Message;
use OPNsense\Base\BaseModel;

/* For consistency with the other frr models, this model should be named STATIC, which is a reserved keyword */
class STATICd extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        foreach ($this->routes->route->iterateItems() as $route) {
            if (!$validateFullModel && !$route->isFieldChanged()) {
                continue;
            }
            $key = $route->__reference;
            if (!empty((string)$route->network) && !empty((string)$route->gateway)) {
                $net_proto = str_contains($route->network, ':') ? 'inet6' : 'inet';
                $gw_proto = str_contains($route->gateway, ':') ? 'inet6' : 'inet';
                if ($net_proto != $gw_proto) {
                    $messages->appendMessage(
                        new Message(gettext("Gateway IP protocol should match network protocol"), $key . ".gateway")
                    );
                }
            }
            if (empty((string)$route->gateway) && empty((string)$route->interfacename)) {
                $messages->appendMessage(
                    new Message(
                        gettext("When no interface is provided, at least a gateway must be offered"),
                        $key . ".gateway"
                    )
                );
            }
        }
        return $messages;
    }
}
