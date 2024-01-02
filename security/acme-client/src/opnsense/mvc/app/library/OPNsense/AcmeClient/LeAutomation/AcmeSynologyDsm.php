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
 * Run acme.sh deploy hook synology_dsm
 * @package OPNsense\AcmeClient
 */
class AcmeSynologyDsm extends Base implements LeAutomationInterface
{
    public function prepare()
    {
        $this->acme_env['SYNO_Certificate'] = 'OPNsense ACME cert ' . $this->cert_id;
        $this->acme_env['SYNO_Hostname'] = (string)$this->config->acme_synology_dsm_hostname;
        $this->acme_env['SYNO_Port'] = (string)$this->config->acme_synology_dsm_port;
        $this->acme_env['SYNO_Scheme'] = (string)$this->config->acme_synology_dsm_scheme;
        $this->acme_env['SYNO_Username'] = (string)$this->config->acme_synology_dsm_username;
        $this->acme_env['SYNO_Password'] = (string)$this->config->acme_synology_dsm_password;
        if (!empty((string)$this->config->acme_synology_dsm_create)) {
            $this->acme_env['SYNO_Create'] = (string)$this->config->acme_synology_dsm_create;
        }
        if (!empty((string)$this->config->acme_synology_dsm_deviceid)) {
            $this->acme_env['SYNO_Device_ID'] = (string)$this->config->acme_synology_dsm_deviceid;
        }
        if (!empty((string)$this->config->acme_synology_dsm_devicename)) {
            $this->acme_env['SYNO_Device_Name'] = (string)$this->config->acme_synology_dsm_devicename;
        }
        $this->acme_args[] = '--deploy-hook synology_dsm';
        return true;
    }
}
