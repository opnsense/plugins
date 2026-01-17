<?php

/*
 * Copyright (C) 2026 OPNsense Community
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

/**
 * Custom DNS API Script
 * Allows users to specify their own acme.sh DNS API script
 * @package OPNsense\AcmeClient
 */
class DnsCustom extends Base implements LeValidationInterface
{
    /**
     * Prepare custom DNS validation
     * Sets environment variables for custom DNS script
     */
    public function prepare()
    {
        // Get the custom script name
        $script_name = trim((string)$this->config->dns_custom_script);

        // If no script name provided, log error and return
        if (empty($script_name)) {
            LeUtils::log_error("DnsCustom: No custom DNS script name provided");
            return;
        }

        // Ensure script name starts with dns_
        if (strpos($script_name, 'dns_') !== 0) {
            $script_name = 'dns_' . $script_name;
        }

        // Override the dns_service in acme_args with custom script name
        $found = false;
        foreach ($this->acme_args as $key => $arg) {
            if (preg_match('/^--dns\s/', $arg) || strpos($arg, '--dns ') === 0) {
                $this->acme_args[$key] = LeUtils::execSafe('--dns %s', $script_name);
                $found = true;
                break;
            }
        }

        // If --dns arg wasn't found, add it
        if (!$found) {
            $this->acme_args[] = LeUtils::execSafe('--dns %s', $script_name);
        }

        // Set optional environment variables if provided
        $env_vars = [
            ['dns_custom_env1_name', 'dns_custom_env1_value'],
            ['dns_custom_env2_name', 'dns_custom_env2_value'],
            ['dns_custom_env3_name', 'dns_custom_env3_value'],
            ['dns_custom_env4_name', 'dns_custom_env4_value'],
        ];

        foreach ($env_vars as $env) {
            $name = trim((string)$this->config->{$env[0]});
            $value = trim((string)$this->config->{$env[1]});
            if (!empty($name) && !empty($value)) {
                $this->acme_env[$name] = $value;
            }
        }
    }
}
