<?php

/*
 * Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\Dnscryptproxy;

use OPNsense\Base\BaseModel;
use OPNsense\Core\Backend;
use Phalcon\Messages\Message;

class General extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        if (
            ($validateFullModel || $this->enabled->isFieldChanged() || $this->listen_addresses->isFieldChanged()) &&
            !empty((string)$this->enabled)
        ) {
            $any4 = [];
            $any6 = [];
            $ports = [];

            /* grab ALL ports to run a validation against, safer for user in the long run */
            foreach (explode(',', (string)$this->listen_addresses) as $addrport) {
                if (preg_match('/(.*):([\d]+)$/', $addrport, $matches)) {
                    $ports[$matches[2]] = 1;
                    if ($matches[1] == '0.0.0.0') {
                        $any4[$matches[2]] = 1;
                    } elseif ($matches[1] == '[::]') {
                        $any6[$matches[2]] = 1;
                    }
                }
            }

            foreach (json_decode((new Backend())->configdpRun('service list'), true) as $service) {
                if (empty($service['dns_ports'])) {
                    continue;
                }
                if (!is_array($service['dns_ports'])) {
                    syslog(LOG_ERR, sprintf('Service %s (%s) reported a faulty "dns_ports" entry.', $service['description'], $service['name']));
                    continue;
                }
                if ($service['name'] != 'dnscrypt-proxy' && count(array_intersect(array_keys($ports), $service['dns_ports']))) {
                    $messages->appendMessage(new Message(
                        sprintf(gettext('%s is currently using one of these ports.'), $service['description']),
                        $this->listen_addresses->getInternalXMLTagName()
                    ));
                    break;
                }
            }

            if (count(array_keys(array_intersect_key($any4, $any6)))) {
                $messages->appendMessage(new Message(
                    gettext('Cannot configure on both "0.0.0.0" and "::" as the first occurence will be treated as dual-stack.'),
                    $this->listen_addresses->getInternalXMLTagName()
                ));
            }
        }

        return $messages;
    }
}
