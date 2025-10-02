<?php

/*
 * Copyright (C) 2020-2024 Frank Wall
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
 * Google Cloud DNS API
 * @package OPNsense\AcmeClient
 */
class DnsGcloud extends Base implements LeValidationInterface
{
    public function prepare()
    {
        // Google Cloud SDK must be installed.
        if ((string)$this->model->isPluginInstalled('google-cloud-sdk') != '1') {
            LeUtils::log_error('Google Cloud SDK plugin is NOT installed. Please install os-google-cloud-sdk and try again.');
            return false;
        }

        // A valid Google Cloud JSON key is required.
        if (!empty((string)$this->config->dns_gcloud_key)) {
            # Extract the gcloud project from the key data.
            $_gcloud_data = json_decode((string)$this->config->dns_gcloud_key);
            $gcloud_project = $_gcloud_data->project_id;
            $gcloud_account = $_gcloud_data->client_email;
            if (empty($gcloud_project)) {
                LeUtils::log_error('unable to extract project name from Google Cloud DNS JSON key');
                return false;
            } else {
                LeUtils::log("Google Cloud DNS project name: {$gcloud_project}");
            }
        } else {
            LeUtils::log('no key for Google Cloud DNS was specified');
            return false;
        }

        // Preparations to run gcloud CLI.
        // NOTE: Never versions of gcloud SDK no longer allow dots in config names.
        $val_id = str_replace('.', '-', (string)$this->config->id);
        $gcloud_config = "acme-{$val_id}";
        $gcloud_key_file = '/tmp/acme_' . (string)$this->config->dns_service . "_{$val_id}.json";
        file_put_contents($gcloud_key_file, (string)$this->config->dns_gcloud_key);
        chmod($gcloud_key_file, 0600);
        $proc_env['CLOUDSDK_PYTHON'] = '/usr/local/bin/python3';
        $proc_env['CLOUDSDK_ACTIVE_CONFIG_NAME'] = $gcloud_config;
        $proc_env['CLOUDSDK_CORE_PROJECT'] = $gcloud_project;

        // Ensure that a working gcloud config exists.
        LeUtils::run_shell_command("/usr/local/bin/gcloud --quiet config configurations create {$gcloud_config}", $proc_env);
        LeUtils::run_shell_command("/usr/local/bin/gcloud --quiet config configurations activate {$gcloud_config}", $proc_env);
        LeUtils::run_shell_command("/usr/local/bin/gcloud --quiet auth activate-service-account --key-file={$gcloud_key_file}", $proc_env);
        LeUtils::run_shell_command("/usr/local/bin/gcloud --quiet config set account {$gcloud_account}", $proc_env);
        LeUtils::run_shell_command("/usr/local/bin/gcloud --quiet config set project {$gcloud_project}", $proc_env);

        // Save config for acme client.
        $this->acme_env['CLOUDSDK_PYTHON'] = '/usr/local/bin/python3';
        $this->acme_env['CLOUDSDK_ACTIVE_CONFIG_NAME'] = $gcloud_config;
        $this->acme_env['CLOUDSDK_CORE_PROJECT'] = $gcloud_project;
    }
}
