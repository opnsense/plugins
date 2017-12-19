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

use \OPNsense\Postfix\Recipient;
use \OPNsense\Core\Config;
use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Base\UIModelGrid;

class RecipientController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'Recipient';
    static protected $internalModelClass = '\OPNsense\Postfix\Recipient';

    public function getAction()
    {
        // define list of configurable settings
        $result = array();
        if ($this->request->isGet()) {
            $mdlRecipient = new Recipient();
            $result['recipient'] = $mdlRecipient->getNodes();
        }
        return $result;
    }

    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            // load model and update with provided data
            $mdlRecipient = new Recipient();
            $mdlRecipient->setNodes($this->request->getPost("recipient"));
            // perform validation
            $valMsgs = $mdlRecipient->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["recipient.".$msg->getField()] = $msg->getMessage();
            }
            // serialize model to config and save
            if ($valMsgs->count() == 0) {
                $mdlRecipient->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function searchRecipientAction()
    {
        $this->sessionClose();
        $mdlRecipient = $this->getModel();
        $grid = new UIModelGrid($mdlRecipient->recipients->recipient);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "address", "action" )
        );
    }

    public function getRecipientAction($uuid = null)
    {
        $mdlRecipient = $this->getModel();
        if ($uuid != null) {
            $node = $mdlRecipient->getNodeByReference('recipients.recipient.' . $uuid);
            if ($node != null) {
                // return node
                return array("recipient" => $node->getNodes());
            }
        } else {
            $node = $mdlRecipient->recipients->recipient->add();
            return array("recipient" => $node->getNodes());
        }
        return array();
    }

    public function addRecipientAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("recipient")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlRecipient = $this->getModel();
            $node = $mdlRecipient->recipients->recipient->Add();
            $node->setNodes($this->request->getPost("recipient"));
            $valMsgs = $mdlRecipient->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "recipient", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (count($result['validations']) == 0) {
                unset($result['validations']);
                // save config if validated correctly
                $mdlRecipient->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function delRecipientAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlRecipient = $this->getModel();
            if ($uuid != null) {
                if ($mdlRecipient->recipients->recipient->del($uuid)) {
                    $mdlRecipient->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    public function setRecipientAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("recipient")) {
            $mdlSetting = $this->getModel();
            if ($uuid != null) {
                $node = $mdlSetting->getNodeByReference('recipients.recipient.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $recipientInfo = $this->request->getPost("recipient");
                    $node->setNodes($recipientInfo);
                    $valMsgs = $mdlSetting->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "recipient", $msg->getField());
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

    public function toggleRecipientAction($uuid)
    {
        return $this->toggle_handler($uuid, 'recipients', 'recipient');
    }
}
