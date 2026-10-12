<?php

/*
 * Copyright (C) 2026 VEQNORA
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

namespace OPNsense\PPPoEServer\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\PPPoEServer\PPPoEServer;

/**
 * Class ServiceController pppoe_server service actions
 * @package OPNsense\PPPoEServer\Api
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\PPPoEServer\PPPoEServer';
    protected static $internalServiceTemplate = 'OPNsense/PPPoEServer';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceName = 'pppoe_server';

    /**
     * ensure the management console credential exists before templates
     * are rendered, then continue with the standard reconfigure flow
     * @return array
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $mdl = new PPPoEServer();
            if ((string)$mdl->general->consolepass == '') {
                // mpd5 stores the console password in a 32-byte buffer
                // (console.h: char password[32]); keep well under 31 chars
                $mdl->general->consolepass = bin2hex(random_bytes(14));
                $mdl->serializeToConfig(false, true);
                Config::getInstance()->save();
            }
        }
        $result = parent::reconfigureAction();
        if ($this->request->isPost()) {
            // (de)register the dynamic 'pppoe' interface group so the
            // administrator can target it with firewall rules immediately,
            // without waiting for a reboot
            $backend = new Backend();
            $backend->configdRun('interface invoke registration');
            // bring the optional CoA listener in line with its config
            $mdl = new PPPoEServer();
            if ((string)$mdl->general->enabled == '1' && (string)$mdl->radius->coa->enabled == '1') {
                $backend->configdRun('pppoe_server coa_restart');
            } else {
                $backend->configdRun('pppoe_server coa_stop');
            }
        }
        return $result;
    }
}
