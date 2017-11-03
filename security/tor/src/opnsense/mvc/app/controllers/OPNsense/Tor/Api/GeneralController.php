<?php

/*
 * Copyright (C) 2017 Fabian Franz
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

use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Core\Config;
use \OPNsense\Tor\General;
use \OPNsense\Base\UIModelGrid;

class GeneralController extends ApiMutableModelControllerBase
{
    static protected $internalModelClass = '\OPNsense\Tor\General';
    static protected $internalModelName = 'general';

    /* override default set action */
    public function setAction()
    {
        $result = array('result'=>'failed');
        if ($this->request->isPost()) {
            $mdl = new General();
            $mdl->setNodes($this->request->getPost('general'));

            // perform validation
            $valMsgs = $mdl->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists('validations', $result)) {
                    $result['validations'] = array();
                }
                $result['validations']['general.'.$msg->getField()] = $msg->getMessage();
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
        $this->sessionClose();
        $mdl = $this->getModel();
        $grid = new UIModelGrid($mdl->client_authentications->client_auth);
        return $grid->fetchBindRequest(
            $this->request,
            array('enabled', 'onion_service', 'auth_cookie')
        );
    }

    public function gethidservauthAction($uuid = null)
    {
        $mdl = $this->getModel();
        if ($uuid != null) {
            $node = $mdl->getNodeByReference('client_authentications.client_auth.' . $uuid);
            if ($node != null) {
                // return node
                return array('client_auth' => $node->getNodes());
            }
        } else {
            $node = $mdl->client_authentications->client_auth->add();
            return array('client_auth' => $node->getNodes());
        }
        return array();
    }

    public function addhidservauthAction()
    {
        $result = array('result' => 'failed');
        if ($this->request->isPost() && $this->request->hasPost('client_auth')) {
            $result = array('result' => 'failed', 'validations' => array());
            $mdl = $this->getModel();
            $node = $mdl->client_authentications->client_auth->Add();
            $node->setNodes($this->request->getPost('client_auth'));
            $valMsgs = $mdl->performValidation();

            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, 'client_auth', $msg->getField());
                $result['validations'][$fieldnm] = $msg->getMessage();
            }

            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdl->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result['result'] = 'saved';
            }
        }
        return $result;
    }

    public function delhidservauthAction($uuid)
    {

        $result = array('result' => 'failed');

        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                if ($mdl->client_authentications->client_auth->del($uuid)) {
                    $mdl->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    public function sethidservauthAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost('client_auth')) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                $node = $mdl->getNodeByReference('client_authentications.client_auth.' . $uuid);
                if ($node != null) {
                    $result = array('result' => 'failed', 'validations' => array());
                    $info = $this->request->getPost('client_auth');

                    $node->setNodes($info);
                    $valMsgs = $mdl->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, 'client_auth', $msg->getField());
                        $result['validations'][$fieldnm] = $msg->getMessage();
                    }

                    if (count($result['validations']) == 0) {
                        // save config if validated correctly
                        $mdl->serializeToConfig();
                        unset($result['validations']);
                        Config::getInstance()->save();
                        $result = array('result' => 'saved');
                    }
                    return $result;
                }
            }
        }
        return array('result' => 'failed');
    }

    public function toggle_handler($uuid, $element)
    {

        $result = array('result' => 'failed');

        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                $node = $mdl->getNodeByReference($element . '.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == '1') {
                        $result['result'] = 'Disabled';
                        $node->enabled = '0';
                    } else {
                        $result['result'] = 'Enabled';
                        $node->enabled = '1';
                    }
                    $mdl->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    public function togglehidservauthAction($uuid)
    {
        return $this->toggle_handler($uuid, 'client_authentications.client_auth');
    }
}
