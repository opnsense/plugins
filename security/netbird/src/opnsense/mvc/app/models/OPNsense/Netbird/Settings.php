<?php

/*
 * Copyright (C) 2025 Ralph Moser, PJ Monitoring GmbH
 * Copyright (C) 2025 squared GmbH
 * Copyright (C) 2025 Christopher Linn, BackendMedia IT-Services GmbH
 * Copyright (C) 2025 NetBird GmbH
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

namespace OPNsense\Netbird;

use OPNsense\Base\BaseModel;

class Settings extends BaseModel
{
    public function syncConfig($target = '/var/db/netbird/default.json')
    {
        $config = json_decode(file_get_contents($target), true);
        if (!is_array($config)) {
            $jsonError = json_last_error_msg();
            syslog(LOG_ERR, "netbird: failed to decode configuration: $jsonError");
            return;
        }

        $config["WgPort"] = (int)$this->general->wireguardPort->__toString();
        $config["ServerSSHAllowed"] = $this->ssh->enable->__toString() == 1;
        $config["DisableFirewall"] = $this->firewall->allowConfig->__toString() != 1;
        $config["BlockInbound"] = $this->firewall->blockInboundConnection->__toString() == 1;
        $config["DisableDNS"] = $this->dns->enable->__toString() != 1;
        $config["BlockLANAccess"] = $this->routing->accessLan->__toString() != 1;
        $config["DisableClientRoutes"] = $this->routing->acceptClientRoutes->__toString() != 1;
        $config["DisableServerRoutes"] = $this->routing->acceptServerRoutes->__toString() != 1;
        $config["RosenpassEnabled"] = $this->postquantum->enableRosenpass->__toString() == 1;
        $config["RosenpassPermissive"] = $this->postquantum->rosenpassPermissive->__toString() == 1;


        $result = file_put_contents($target, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($result === false) {
            syslog(LOG_ERR, "netbird: failed to write updated configuration to $target");
        }
    }
}
