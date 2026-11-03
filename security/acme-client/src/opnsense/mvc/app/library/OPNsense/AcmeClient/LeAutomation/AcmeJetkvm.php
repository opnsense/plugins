<?php

/*
 * Copyright (C) 2026 daemonhorn
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

namespace OPNsense\AcmeClient\LeAutomation;

use OPNsense\AcmeClient\LeAutomationInterface;

/**
 * Run acme.sh deploy hook jetkvm
 * @package OPNsense\AcmeClient
 */
class AcmeJetkvm extends Base implements LeAutomationInterface
{
    public function prepare()
    {
        // No empty-host guard here: jetkvm.sh itself falls back to the
        // certificate's own domain when DEPLOY_JETKVM_HOST is unset,
        // same as AcmeZyxelGs1900/zyxel_gs1900.sh's DEPLOY_ZYXEL_SWITCH.
        // A wrong fallback still surfaces a real (if less friendly) SSH
        // connection error in the ACME Client log.
        $this->acme_env['DEPLOY_JETKVM_HOST'] = (string)$this->config->acme_jetkvm_host;
        $this->acme_env['DEPLOY_JETKVM_USER'] = (string)$this->config->acme_jetkvm_user;
        $this->acme_env['DEPLOY_JETKVM_PORT'] = (string)$this->config->acme_jetkvm_port;
        $this->acme_env['DEPLOY_JETKVM_RESTART_CMD'] = ((string)$this->config->acme_jetkvm_reboot == 1) ? 'reboot' : 'none';
        $this->acme_args[] = '--deploy-hook jetkvm';
        return true;
    }
}
