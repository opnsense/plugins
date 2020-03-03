<?php

/*
 * Copyright (C) 2020 Deciso B.V.
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

namespace OPNsense\Firewall;

use Phalcon\Validation\Message;
use OPNsense\Base\BaseModel;
use OPNsense\Firewall\Util;

class Filter extends BaseModel
{
    /**
     * @inheritDoc
     */
    public function performValidation($validateFullModel = false)
    {
        // standard model validations
        $messages = parent::performValidation($validateFullModel);
        foreach ($this->rules->rule->iterateItems() as $rule) {
            // validate changed rules
            $rule_changed = false;
            foreach($rule->iterateItems() as $field) {
                $rule_changed = $rule_changed ? $rule_changed : $field->isFieldChanged();
            }
            if ($validateFullModel || $rule_changed) {
                // port / protocol validation
                if (!empty((string)$rule->source_port) && !in_array( $rule->protocol, ['TCP', 'UDP'])) {
                    $messages->appendMessage(new Message(
                        gettext("Source ports are only valid for tcp or udp type rules."),
                        $rule->source_port->__reference
                    ));
                }
                if (!empty((string)$rule->destination_port) && !in_array( $rule->protocol, ['TCP', 'UDP'])) {
                    $messages->appendMessage(new Message(
                        gettext("Destination ports are only valid for tcp or udp type rules."),
                        $rule->destination_port->__reference
                    ));
                }
                // validate protocol family
                $dest_is_addr = Util::isSubnet($rule->destination_net) || Util::isIpAddress($rule->destination_net);
                $dest_proto = strpos($rule->destination_net, ':') === false ? "inet" : "inet6";
                if ($dest_is_addr && $dest_proto != $rule->ipprotocol) {
                    $messages->appendMessage(new Message(
                        gettext("Destination address type should match selected TCP/IP protocol version."),
                        $rule->destination_net->__reference
                    ));
                }
                $src_is_addr = Util::isSubnet($rule->source_net) || Util::isIpAddress($rule->source_net);
                $src_proto = strpos($rule->source_net, ':') === false ? "inet" : "inet6";
                if ($src_is_addr && $src_proto != $rule->ipprotocol) {
                    $messages->appendMessage(new Message(
                        gettext("Source address type should match selected TCP/IP protocol version."),
                        $rule->source_net->__reference
                    ));
                }

            }
        }
        return $messages;
    }
}