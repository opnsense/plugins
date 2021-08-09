<?php

/*
 * Copyright (C) 2015-2017 Deciso B.V.
 * Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Freeradius\Api;

use OPNsense\Freeradius\Client;
use OPNsense\Core\Config;
use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;

class ClientController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'Client';
    protected static $internalModelClass = '\OPNsense\Freeradius\Client';
    public function getAction()
    {
        // define list of configurable settings
        $result = array();
        if ($this->request->isGet()) {
            $mdlClient = new Client();
            $result['client'] = $mdlClient->getNodes();
        }
        return $result;
    }

    public function setAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            // load model and update with provided data
            $mdlClient = new Client();
            $mdlClient->setNodes($this->request->getPost("client"));
            // perform validation
            $valMsgs = $mdlClient->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["client." . $msg->getField()] = $msg->getMessage();
            }
            // serialize model to config and save
            if ($valMsgs->count() == 0) {
                $mdlClient->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function searchClientAction()
    {
        $this->sessionClose();
        $mdlClient = $this->getModel();
        $grid = new UIModelGrid($mdlClient->clients->client);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "secret", "ip" )
        );
    }

    public function getClientAction($uuid = null)
    {
        $mdlClient = $this->getModel();
        if ($uuid != null) {
            $node = $mdlClient->getNodeByReference('clients.client.' . $uuid);
            if ($node != null) {
                // return node
                return array("client" => $node->getNodes());
            }
        } else {
            $node = $mdlClient->clients->client->add();
            return array("client" => $node->getNodes());
        }
        return array();
    }

    public function addClientAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("client")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlClient = $this->getModel();
            $node = $mdlClient->clients->client->Add();
            $node->setNodes($this->request->getPost("client"));
            $valMsgs = $mdlClient->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "client", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (count($result['validations']) == 0) {
                unset($result['validations']);
                // save config if validated correctly
                $mdlClient->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function delClientAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlClient = $this->getModel();
            if ($uuid != null) {
                if ($mdlClient->clients->client->del($uuid)) {
                    $mdlClient->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    public function setClientAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("client")) {
            $mdlSetting = $this->getModel();
            if ($uuid != null) {
                $node = $mdlSetting->getNodeByReference('clients.client.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $clientInfo = $this->request->getPost("client");
                    $node->setNodes($clientInfo);
                    $valMsgs = $mdlSetting->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "client", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }
                    if (count($result['validations']) == 0) {
                        // save config if validated correctly
                        $mdlSetting->serializeToConfig();
                        Config::getInstance()->save();
                        $result = array("result" => "saved");
                    }
                    return $result;
                }
            }
        }
        return array("result" => "failed");
    }

    public function toggle_handler($uuid, $elements, $element)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlSetting = $this->getModel();
            if ($uuid != null) {
                $node = $mdlSetting->getNodeByReference($elements . '.' . $element . '.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $result['result'] = "Disabled";
                        $node->enabled = "0";
                    } else {
                        $result['result'] = "Enabled";
                        $node->enabled = "1";
                    }
                    // if item has toggled, serialize to config and save
                    $mdlSetting->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    public function toggleClientAction($uuid)
    {
        return $this->toggle_handler($uuid, 'clients', 'client');
    }
}
