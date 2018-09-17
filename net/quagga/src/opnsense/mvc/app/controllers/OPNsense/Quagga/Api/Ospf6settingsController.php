<?php

/*
 *    Copyright (C) 2015-2017 Deciso B.V.
 *    Copyright (C) 2015 Jos Schellevis
 *    Copyright (C) 2017 Fabian Franz
 *    Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
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
 */

namespace OPNsense\Quagga\Api;

use \OPNsense\Quagga\OSPF6;
use \OPNsense\Core\Config;
use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Base\UIModelGrid;

class Ospf6settingsController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'OSPF6';
    static protected $internalModelClass = '\OPNsense\Quagga\OSPF6';
    public function getAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdlospf6 = new OSPF6();
            $result['ospf6'] = $mdlospf6->getNodes();
        }
        return $result;
    }
    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            // load model and update with provided data
            $mdlospf6 = new OSPF6();
            $mdlospf6->setNodes($this->request->getPost("ospf6"));
            // perform validation
            $valMsgs = $mdlospf6->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["general.".$msg->getField()] = $msg->getMessage();
            }
            // serialize model to config and save
            if ($valMsgs->count() == 0) {
                $mdlospf6->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }
/////////////////////////////////////////////////////////////////////
    public function searchInterfaceAction()
    {
        $this->sessionClose();
        $mdlOSPF6 = $this->getModel();
        $grid = new UIModelGrid($mdlOSPF6->interfaces->interface);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "interfacename", "area", "networktype")
        );
    }
    public function getInterfaceAction($uuid = null)
    {
        $mdlOSPF6 = $this->getModel();
        if ($uuid != null) {
            $node = $mdlOSPF6->getNodeByReference('interfaces.interface.' . $uuid);
            if ($node != null) {
                // return node
                return array("interface" => $node->getNodes());
            }
        } else {
            $node = $mdlOSPF6->interfaces->interface->add();
            return array("interface" => $node->getNodes());
        }
        return array();
    }
    public function addInterfaceAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("interface")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlOSPF6 = $this->getModel();
            $node = $mdlOSPF6->interfaces->interface->Add();
            $node->setNodes($this->request->getPost("interface"));
            $valMsgs = $mdlOSPF6->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "interface", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdlOSPF6->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result["result"] = "saved";
            }
        }
        return $result;
    }
    public function delInterfaceAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlOSPF6 = $this->getModel();
            if ($uuid != null) {
                if ($mdlOSPF6->interfaces->interface->del($uuid)) {
                    $mdlOSPF6->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }
    public function setInterfaceAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("interface")) {
            $mdlNetwork = $this->getModel();
            if ($uuid != null) {
                $node = $mdlNetwork->getNodeByReference('interfaces.interface.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $interfaceInfo = $this->request->getPost("interface");
                    $node->setNodes($interfaceInfo);
                    $valMsgs = $mdlNetwork->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "interface", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }
                    if (count($result['validations']) == 0) {
                        // save config if validated correctly
                        $mdlNetwork->serializeToConfig();
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
            $mdlNetwork = $this->getModel();
            if ($uuid != null) {
                $node = $mdlNetwork->getNodeByReference($elements . '.'. $element .'.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $result['result'] = "Disabled";
                        $node->enabled = "0";
                    } else {
                        $result['result'] = "Enabled";
                        $node->enabled = "1";
                    }
                    // if item has toggled, serialize to config and save
                    $mdlNetwork->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }
    public function toggleInterfaceAction($uuid)
    {
        return $this->toggle_handler($uuid, 'interfaces', 'interface');
    }
}
