<?php
/**
 *    Copyright (C) 2015 - 2017 Deciso B.V.
 *    Copyright (C) 2017 Michael Muenz
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Postfix\Api;

use \OPNsense\Postfix\Sender;
use \OPNsense\Core\Config;
use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Base\UIModelGrid;

class SenderController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'Sender';
    static protected $internalModelClass = '\OPNsense\Postfix\Sender';

    public function getAction()
    {
        // define list of configurable settings
        $result = array();
        if ($this->request->isGet()) {
            $mdlSender = new Sender();
            $result['sender'] = $mdlSender->getNodes();
        }
        return $result;
    }

    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            // load model and update with provided data
            $mdlSender = new Sender();
            $mdlSender->setNodes($this->request->getPost("sender"));
            // perform validation
            $valMsgs = $mdlSender->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["sender.".$msg->getField()] = $msg->getMessage();
            }
            // serialize model to config and save
            if ($valMsgs->count() == 0) {
                $mdlSender->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function searchSenderAction()
    {
        $this->sessionClose();
        $mdlSender = $this->getModel();
        $grid = new UIModelGrid($mdlSender->senders->sender);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "address", "action" )
        );
    }

    public function getSenderAction($uuid = null)
    {
        $mdlSender = $this->getModel();
        if ($uuid != null) {
            $node = $mdlSender->getNodeByReference('senders.sender.' . $uuid);
            if ($node != null) {
                // return node
                return array("sender" => $node->getNodes());
            }
        } else {
            $node = $mdlSender->senders->sender->add();
            return array("sender" => $node->getNodes());
        }
        return array();
    }

    public function addSenderAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("sender")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlSender = $this->getModel();
            $node = $mdlSender->senders->sender->Add();
            $node->setNodes($this->request->getPost("sender"));
            $valMsgs = $mdlSender->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "sender", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (count($result['validations']) == 0) {
                unset($result['validations']);
                // save config if validated correctly
                $mdlSender->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function delSenderAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlSender = $this->getModel();
            if ($uuid != null) {
                if ($mdlSender->senders->sender->del($uuid)) {
                    $mdlSender->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    public function setSenderAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("sender")) {
            $mdlSetting = $this->getModel();
            if ($uuid != null) {
                $node = $mdlSetting->getNodeByReference('senders.sender.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $senderInfo = $this->request->getPost("sender");
                    $node->setNodes($senderInfo);
                    $valMsgs = $mdlSetting->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "sender", $msg->getField());
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
                $node = $mdlSetting->getNodeByReference($elements . '.'. $element .'.' . $uuid);
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

    public function toggleSenderAction($uuid)
    {
        return $this->toggle_handler($uuid, 'senders', 'sender');
    }
}
