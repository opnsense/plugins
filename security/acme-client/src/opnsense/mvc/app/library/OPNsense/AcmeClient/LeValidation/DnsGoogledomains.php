<?php

/*
 * Copyright (C) 2023 Cannon Matthews <cannonmatthews@google.com>
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
 * Google Domains DNS API
 * @package OPNsense\AcmeClient
 */
class DnsGoogleDomains extends Base implements LeValidationInterface
{
    public function prepare()
    {
        // It is possible to override $GOOGLEDOMAINS_API env variable to
        // control the endpoint acme.sh talks to. However there is only one
        // option (https://acmedns.googleapis.com/v1/acmeChallengeSets) that is
        // currently the default, so exposing this only adds to confusion and
        // noise in the UI.
        $this->acme_env['GOOGLEDOMAINS_ACCESS_TOKEN'] = (string)$this->config->dns_googledomains_access_token;
        $this->acme_env['GOOGLEDOMAINS_ZONE'] = (string)$this->config->dns_googledomains_zone;
    }
}
