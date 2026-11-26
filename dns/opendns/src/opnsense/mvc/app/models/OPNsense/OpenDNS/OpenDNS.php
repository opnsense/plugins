<?php

/*
 * Copyright (C) 2026 Greelan
 * Copyright (c) 2015-2021 Franco Fichtner <franco@opnsense.org>
 * Copyright (c) 2008 Tellnet AG
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

namespace OPNsense\OpenDNS;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Config;
use OPNsense\Interfaces\Settings;

class OpenDNS extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        if ($this->enable->isEmpty()) {
            return $messages;
        }
        foreach (['username', 'password', 'host'] as $fieldname) {
            $node = $this->$fieldname;
            if ($validateFullModel || $this->enable->isFieldChanged() || $node->isFieldChanged()) {
                if ($node->isEmpty()) {
                    $messages->appendMessage(new Message(
                        gettext('A value is required when OpenDNS is enabled.'),
                        $fieldname
                    ));
                }
            }
        }
        return $messages;
    }

    /**
     * point the system DNS servers to OpenDNS when enabled and clear them when disabled, standalone
     * mode leaves them alone. Core has no hook for plugin supplied nameservers, so the system section
     * is written here, as the InitialSetup model does.
     */
    public function updateSystemDns()
    {
        if (!$this->standalone->isEmpty()) {
            return;
        }
        if ($this->enable->isEmpty() && $this->enable->getInitialValue() !== '1') {
            /* not enabled before this save either, the system DNS servers are not ours to clear */
            return;
        }

        $system = Config::getInstance()->object()->system;
        if (!$this->enable->isEmpty()) {
            $v4_server = ['208.67.222.222', '208.67.220.220'];
            $v6_server = (new Settings())->disableipv6->isEmpty() ? ['2620:119:35::35', '2620:119:53::53'] : [];
            $servers = array_merge($v6_server, $v4_server);
            if (isset($system->prefer_ipv4)) {
                $servers = array_merge($v4_server, $v6_server);
            }
            $allowoverride = '0';
        } else {
            $servers = [''];
            $allowoverride = '1';
        }

        unset($system->dnsserver);
        foreach ($servers as $server) {
            $system->addChild('dnsserver', $server);
        }
        $system->dnsallowoverride = $allowoverride;
    }
}
