<?php

/*
 * Copyright (C) 2020-2025 Erwin Witbreuk
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
 * bHosted.nl DNS API
 * @package OPNsense\AcmeClient
 */
class DnsBhosted extends Base implements LeValidationInterface
{
    public function prepare()
    {
        $this->acme_env['BHOSTED_Username'] = (string)$this->config->dns_bhosted_user;
        $this->acme_env['BHOSTED_Password'] = (string)$this->config->dns_bhosted_password;
        // Optional TTL
        if (!empty((string)$this->config->dns_bhosted_ttl)) {
            $this->acme_env['BHOSTED_TTL'] = (string)$this->config->dns_bhosted_ttl;
        }
        // Optional SLD
        if (!empty((string)$this->config->dns_bhosted_sld)) {
            $this->acme_env['BHOSTED_SLD'] = (string)$this->config->dns_bhosted_sld;
        }
        // Optional TLD
        if (!empty((string)$this->config->dns_bhosted_tld)) {
            $this->acme_env['BHOSTED_TLD'] = (string)$this->config->dns_bhosted_tld;
        }
    }
}
