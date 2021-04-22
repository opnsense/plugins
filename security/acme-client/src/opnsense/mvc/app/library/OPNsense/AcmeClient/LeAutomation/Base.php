<?php

/*
 * Copyright (C) 2020 Frank Wall
 * Copyright (C) 2018 Deciso B.V.
 * Copyright (C) 2018 Franco Fichtner <franco@opnsense.org>
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

use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\AcmeClient\LeAccount;
use OPNsense\AcmeClient\LeUtils;

/**
 * LeAutomation stub file, contains shared logic for all automations.
 * @package OPNsense\AcmeClient
 */
abstract class Base extends \OPNsense\AcmeClient\LeCommon
{
    public const CONFIG_PATH = 'actions.action';

    /**
     * Initialize LeAutomation object by adding the required configuration.
     * @return boolean
     */
    public function init(string $certid, string $accountuuid)
    {
        // Get config object
        $this->loadConfig(self::CONFIG_PATH, $this->uuid);

        // Get account object to query ID
        $account = new LeAccount($accountuuid);

        // Store auxiliary information (required to glue stuff together)
        $this->cert_id = $certid;
        $this->account_id = (string)$account->id;
        $this->account_uuid = (string)$account->uuid;

        // Set log level
        $this->setLoglevel();

        // Set Let's Encrypt environment
        $this->setEnvironment();

        return true;
    }

    /**
     * run all tasks related to this automation
     * @return boolean
     */
    public function run()
    {
        if (!($this->isEnabled())) {
            LeUtils::log('ignoring disabled automation: ' . (string)$this->config->name);
            return true; // not an error
        }

        LeUtils::log('running automation: ' . $this->config->name);
        $backend = new \OPNsense\Core\Backend();
        $response = $backend->configdRun((string)$this->command, $this->command_args);
        return true;
    }

    /**
     * get automation type from configuration
     * @return string
     */
    public function getType()
    {
        return $this->config->type;
    }
}
