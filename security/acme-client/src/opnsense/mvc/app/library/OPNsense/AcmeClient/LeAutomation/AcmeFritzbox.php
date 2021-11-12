<?php

/*
 * Copyright (C) 2021 Frank Wall
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
 * Run acme.sh deploy hook fritzbox
 * @package OPNsense\AcmeClient
 */
class AcmeFritzbox extends Base implements LeAutomationInterface
{
    public function prepare()
    {
        $this->acme_env['DEPLOY_FRITZBOX_URL'] = (string)$this->config->acme_fritzbox_url;
        $this->acme_env['DEPLOY_FRITZBOX_USERNAME'] = (string)$this->config->acme_fritzbox_username;
        $this->acme_env['DEPLOY_FRITZBOX_PASSWORD'] = (string)$this->config->acme_fritzbox_password;
        $this->acme_args[] = '--deploy-hook fritzbox';
        return true;
    }
}
