<?php

/*
 * Copyright (C) 2020 Frank Wall
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

namespace OPNsense\AcmeClient\LeValidation;

use OPNsense\AcmeClient\LeValidationInterface;
use OPNsense\AcmeClient\LeUtils;
use OPNsense\Core\Config;

/**
 * OPNsense BIND DNS API
 * @package OPNsense\AcmeClient
 */
class DnsOpnsense extends Base implements LeValidationInterface
{
    public function prepare()
    {
        # BIND plugin must be installed.
        if ((string)$this->model->isPluginInstalled('bind') != '1') {
            LeUtils::log_error('BIND plugin is NOT installed. Please install os-bind and try again.');
            return false;
        }
        $this->acme_env['OPNs_Host'] = (string)$this->config->dns_opnsense_host;
        $this->acme_env['OPNs_Port'] = (string)$this->config->dns_opnsense_port;
        $this->acme_env['OPNs_Key'] = (string)$this->config->dns_opnsense_key;
        $this->acme_env['OPNs_Token'] = (string)$this->config->dns_opnsense_token;
        $this->acme_env['OPNs_Api_Insecure'] = (string)$this->config->dns_opnsense_insecure;
    }
}
