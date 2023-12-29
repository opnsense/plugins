<?php

/*
 * Copyright (C) 2017 Fabian Franz
 * Copyright (C) 2015 Jos Schellevis <jos@opnsense.org>
 * Copyright (C) 2015-2017 Deciso B.V.
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

namespace OPNsense\Tor\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Tor\General;
use OPNsense\Base\UIModelGrid;

class GeneralController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\Tor\General';
    protected static $internalModelName = 'general';

    /* override default set action */
    public function setAction()
    {
        $result = array('result' => 'failed');
        if ($this->request->isPost()) {
            $mdl = new General();
            $mdl->setNodes($this->request->getPost('general'));

            // perform validation
            $valMsgs = $mdl->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists('validations', $result)) {
                    $result['validations'] = array();
                }
                $result['validations']['general.' . $msg->getField()] = $msg->getMessage();
            }

            if ($valMsgs->count() == 0) {
                if (empty((string)$mdl->control_port_password) || empty((string)$mdl->control_port_password_hashed)) {
                    $backend = new Backend();
                    $keys = json_decode(trim($backend->configdRun('tor genkey')), true);
                    $mdl->control_port_password_hashed = $keys['hashed_control_password'];
                    $mdl->control_port_password = $keys['control_password'];
                }
                $mdl->serializeToConfig();
                Config::getInstance()->save();
                $result['result'] = 'saved';
            }
        }
        return $result;
    }

    /*  Hidden service authentication  */

    public function searchhidservauthAction()
    {
        return $this->searchBase('client_authentications.client_auth', array('enabled', 'onion_service', 'auth_cookie'));
    }

    public function gethidservauthAction($uuid = null)
    {
        $this->sessionClose();
        return $this->getBase('client_auth', 'client_authentications.client_auth', $uuid);
    }

    public function addhidservauthAction()
    {
        return $this->addBase('client_auth', 'client_authentications.client_auth');
    }

    public function delhidservauthAction($uuid)
    {
        return $this->delBase('client_authentications.client_auth', $uuid);
    }

    public function sethidservauthAction($uuid)
    {
        return $this->setBase('client_auth', 'client_authentications.client_auth', $uuid);
    }

    public function togglehidservauthAction($uuid)
    {
        return $this->toggleBase('client_authentications.client_auth', $uuid);
    }
}
