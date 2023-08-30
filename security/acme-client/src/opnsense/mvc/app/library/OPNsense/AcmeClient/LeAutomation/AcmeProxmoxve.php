<?php

/*
 * Copyright (C) 2023 Jan Winkler
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
 * Run acme.sh deploy hook proxmoxve
 * @package OPNsense\AcmeClient
 */
class AcmeProxmoxve extends Base implements LeAutomationInterface
{
    public function prepare()
    {
        $this->acme_env['DEPLOY_PROXMOXVE_USER'] = (string)$this->config->acme_proxmoxve_user;
        $this->acme_env['DEPLOY_PROXMOXVE_SERVER'] = (string)$this->config->acme_proxmoxve_server;
        $this->acme_env['DEPLOY_PROXMOXVE_SERVER_PORT'] = (string)$this->config->acme_proxmoxve_port;
        $this->acme_env['DEPLOY_PROXMOXVE_NODE_NAME'] = (string)$this->config->acme_proxmoxve_nodename;
        $this->acme_env['DEPLOY_PROXMOXVE_USER_REALM'] = (string)$this->config->acme_proxmoxve_realm;
        $this->acme_env['DEPLOY_PROXMOXVE_API_TOKEN_NAME'] = (string)$this->config->acme_proxmoxve_tokenid;
        $this->acme_env['DEPLOY_PROXMOXVE_API_TOKEN_KEY'] = (string)$this->config->acme_proxmoxve_tokenkey;
        $this->acme_args[] = '--deploy-hook proxmoxve';
        return true;
    }
}
