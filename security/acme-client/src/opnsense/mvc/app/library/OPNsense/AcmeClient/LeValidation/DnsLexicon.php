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

const PROVIDER_FIELDMAP = [
    // These are the ones that are supported "by default", meaning they have a
    // very standard set of parameters
    "default" => ["user" => "USERNAME", "secret" => "TOKEN"],

    "aliyun" => ["user" => "KEY_ID", "secret" => "SECRET"],
    "aurora" => ["user" => "API_KEY", "secret" => "SECRET_KEY"],
    "dinahosting" => ["user" => "USERNAME", "secret" => "PASSWORD"],
    "euserv" => ["user" => "USERNAME", "secret" => "PASSWORD"],

    "gransy" => ["user" => "USERNAME", "secret" => "PASSWORD"],
    "gratisdns" => ["user" => "USERNAME", "secret" => "PASSWORD"],
    "exoscale" => ["user" => "KEY", "secret" => "SECRET"],

    "internetbs" => ["user" => "KEY", "secret" => "PASSWORD"],
    
    // Note to use hurricane electric DNS you must disable 2-factor auth
    "henet" => ["user" => "USERNAME", "secret" => "PASSWORD"],

    "hover" => ["user" => "USERNAME", "secret" => "PASSWORD"],
    "inwx" => ["user" => "USERNAME", "secret" => "PASSWORD"],
    "webgo" => ["user" => "USERNAME", "secret" => "PASSWORD"],
    // user/token map to auth_token/auth_secret respectively
    "gehirn" => ["user" => "TOKEN", "secret" => "SECRET"],
    "sakuracloud" => ["user" => "TOKEN", "secret" => "SECRET"],
    "softlayer" => ["user" => "TOKEN", "secret" => "API_KEY"],
    
    "godaddy" => ["user" => "KEY", "secret" => "SECRET"],

    "zilore" => ["user" => "USERNAME", "secret" => "KEY"],

    // Set username to the entrypoint (docs say "Use zonomi or rimuhosting api")
    "zonomi" => ["user" => "ENTRYPOINT", "secret" => "TOKEN"],
    
    // I'm not actually sure if this will work wtihout the auth_key_is_global option,
    // but it's not going to work any worse than without this mapping...
    "transip" => ["user" => "USERNAME", "secret" => "API_KEY"],

    // Slightly hacky, but this makes it possible to use this type
    // if you put the DDNS server ip as the user, and the token
    // should be in the format <alg>:<key_id>:<secret> per leixcon docs
    "ddns" => ["user" => "DDNS_SERVER", "secret" => "TOKEN"],

    // This is super hacky; user is ignored, secret should be a base64 encoded string with
    // the servce_account_info.json file prefixed by base64::, e.g. 
    //     "base64::eyjhbgcioyj…"
    "googleclouddns" => ["user" => "USERNAME", "secret" => "AUTH_SERVICE_ACCOUNT_INFO"],

    // Many are still not supported, usually because they require extra parameters.
    // It would be nice later on to add support for custom environment variables which
    // would make it possible to use basically any other providers
];

/**
 * Lexicon DNS API
 * @package OPNsense\AcmeClient
 */
class DnsLexicon extends Base implements LeValidationInterface
{
    public function prepare()
    {
        $provider = (string)$this->config->dns_lexicon_provider;
        $fieldMap = PROVIDER_FIELDMAP[$provider] ?? PROVIDER_FIELDMAP["default"];
        $env_user = 'LEXICON_' . strtoupper($provider) . '_' . $fieldMap["user"];
        $env_token = 'LEXICON_' . strtoupper($provider) . '_' . $fieldMap["secret"];

        $this->acme_env['PROVIDER'] = $provider;
        $this->acme_env[$env_user] = (string)$this->config->dns_lexicon_user;
        $this->acme_env[$env_token] = (string)$this->config->dns_lexicon_token;
    }
}
