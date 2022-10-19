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
use OPNsense\Core\Config;

/**
 * CF DNS API
 * @package OPNsense\AcmeClient
 */
class DnsCf extends Base implements LeValidationInterface
{
    public function prepare()
    {
        // Global API key (insecure)
        $this->acme_env['CF_Key'] = (string)$this->config->dns_cf_key;
        $this->acme_env['CF_Email'] = (string)$this->config->dns_cf_email;
        // Restricted API token (recommended)
        $this->acme_env['CF_Token'] = (string)$this->config->dns_cf_token;
        $this->acme_env['CF_Account_ID'] = (string)$this->config->dns_cf_account_id;
        // Optional Zone ID
        if (!empty((string)$this->config->dns_cf_zone_id)) {
            $this->acme_env['CF_Zone_ID'] = (string)$this->config->dns_cf_zone_id;
        }
    }
}
