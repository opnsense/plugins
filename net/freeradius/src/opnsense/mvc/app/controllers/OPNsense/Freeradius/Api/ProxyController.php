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

use OPNsense\Freeradius\Proxy;
use OPNsense\Core\Config;
use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;

class ProxyController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'Proxy';
    protected static $internalModelClass = '\OPNsense\Freeradius\Proxy';

    public function getAction()
    {
        // define list of configurable settings
        $result = array();
        if ($this->request->isGet()) {
            $mdlProxy = new Proxy();
            $result['proxy'] = $mdlProxy->getNodes();
        }
        return $result;
    }

    public function setAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            // load model and update with provided data
            $mdlProxy = new Proxy();
            $mdlProxy->setNodes($this->request->getPost("proxy"));
            // perform validation
            $valMsgs = $mdlProxy->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["proxy." . $msg->getField()] = $msg->getMessage();
            }
            // serialize model to config and save
            if ($valMsgs->count() == 0) {
                $mdlProxy->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }


    public function searchRealmAction()
    {
        $this->sessionClose();
        $mdlRealm = $this->getModel();
        $grid = new UIModelGrid($mdlRealm->realms->realm);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "auth_pool", "acct_pool", "nostrip" )
        );
    }

    public function getRealmAction($uuid = null)
    {
        $mdlRealm = $this->getModel();
        if ($uuid != null) {
            $node = $mdlRealm->getNodeByReference('realms.realm.' . $uuid);
            if ($node != null) {
                // return node
                return array("realm" => $node->getNodes());
            }
        } else {
            $node = $mdlRealm->realms->realm->add();
            return array("realm" => $node->getNodes());
        }
        return array();
    }

    public function addRealmAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("realm")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlRealm = $this->getModel();
            $node = $mdlRealm->realms->realm->Add();
            $node->setNodes($this->request->getPost("realm"));
            $valMsgs = $mdlRealm->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "realm", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (count($result['validations']) == 0) {
                unset($result['validations']);
                // save config if validated correctly
                $mdlRealm->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function delRealmAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlRealm = $this->getModel();
            if ($uuid != null) {
                if ($mdlRealm->realms->realm->del($uuid)) {
                    $mdlRealm->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    public function setRealmAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("realm")) {
            $mdlSetting = $this->getModel();
            if ($uuid != null) {
                $node = $mdlSetting->getNodeByReference('realms.realm.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $clientInfo = $this->request->getPost("realm");
                    $node->setNodes($clientInfo);
                    $valMsgs = $mdlSetting->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "realm", $msg->getField());
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

    public function toggleRealmAction($uuid)
    {
        return $this->toggle_handler($uuid, 'realms', 'realm');
    }
    public function searchHomeserverAction()
    {
        $this->sessionClose();
        $mdlHomeserver = $this->getModel();
        $grid = new UIModelGrid($mdlHomeserver->homeservers->homeserver);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "type", "addresstype", "ipaddr", "ipaddr6", "ipaddr6", "virtualserver", "port", "proto", "secret", "sourceip", "response_window", "no_response_fail", "zombieperiod", "reviveinterval", "statuscheck", "checkinterval", "numanswersalive", "max_outstanding", "limit_maxconnections", "limit_maxrequests", "limit_lifetime", "limit_idletimeout" )
        );
    }

    public function getHomeserverAction($uuid = null)
    {
        $mdlHomeserver = $this->getModel();
        if ($uuid != null) {
            $node = $mdlHomeserver->getNodeByReference('homeservers.homeserver.' . $uuid);
            if ($node != null) {
                // return node
                return array("homeserver" => $node->getNodes());
            }
        } else {
            $node = $mdlHomeserver->homeservers->homeserver->add();
            return array("homeserver" => $node->getNodes());
        }
        return array();
    }

    public function addHomeserverAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("homeserver")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlHomeserver = $this->getModel();
            $node = $mdlHomeserver->homeservers->homeserver->Add();
            $node->setNodes($this->request->getPost("homeserver"));
            $valMsgs = $mdlHomeserver->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "homeserver", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (count($result['validations']) == 0) {
                unset($result['validations']);
                // save config if validated correctly
                $mdlHomeserver->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function delHomeserverAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlHomeserver = $this->getModel();
            if ($uuid != null) {
                if ($mdlHomeserver->homeservers->homeserver->del($uuid)) {
                    $mdlHomeserver->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    public function setHomeserverAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("homeserver")) {
            $mdlSetting = $this->getModel();
            if ($uuid != null) {
                $node = $mdlSetting->getNodeByReference('homeservers.homeserver.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $clientInfo = $this->request->getPost("homeserver");
                    $node->setNodes($clientInfo);
                    $valMsgs = $mdlSetting->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "homeserver", $msg->getField());
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

    public function toggleHomeserverAction($uuid)
    {
        return $this->toggle_handler($uuid, 'homeservers', 'homeserver');
    }
    public function searchHomeserverpoolAction()
    {
        $this->sessionClose();
        $mdlHomeserverpool = $this->getModel();
        $grid = new UIModelGrid($mdlHomeserverpool->homeserverpools->homeserverpool);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "type", "virtualserver", "virtualserver", "homeservers", "fallback"  )
        );
    }

    public function getHomeserverpoolAction($uuid = null)
    {
        $mdlHomeserverpool = $this->getModel();
        if ($uuid != null) {
            $node = $mdlHomeserverpool->getNodeByReference('homeserverpools.homeserverpool.' . $uuid);
            if ($node != null) {
                // return node
                return array("homeserverpool" => $node->getNodes());
            }
        } else {
            $node = $mdlHomeserverpool->homeserverpools->homeserverpool->add();
            return array("homeserverpool" => $node->getNodes());
        }
        return array();
    }

    public function addHomeserverpoolAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("homeserverpool")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlHomeserverpool = $this->getModel();
            $node = $mdlHomeserverpool->homeserverpools->homeserverpool->Add();
            $node->setNodes($this->request->getPost("homeserverpool"));
            $valMsgs = $mdlHomeserverpool->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "homeserverpool", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (count($result['validations']) == 0) {
                unset($result['validations']);
                // save config if validated correctly
                $mdlHomeserverpool->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function delHomeserverpoolAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlHomeserverpool = $this->getModel();
            if ($uuid != null) {
                if ($mdlHomeserverpool->homeserverpools->homeserverpool->del($uuid)) {
                    $mdlHomeserverpool->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    public function setHomeserverpoolAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("homeserverpool")) {
            $mdlSetting = $this->getModel();
            if ($uuid != null) {
                $node = $mdlSetting->getNodeByReference('homeserverpools.homeserverpool.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $clientInfo = $this->request->getPost("homeserverpool");
                    $node->setNodes($clientInfo);
                    $valMsgs = $mdlSetting->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "homeserverpool", $msg->getField());
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

    public function toggleHomeserverpoolAction($uuid)
    {
        return $this->toggle_handler($uuid, 'homeserverpools', 'homeserverpool');
    }
}
