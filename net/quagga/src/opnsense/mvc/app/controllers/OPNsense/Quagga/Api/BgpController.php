<?php
namespace OPNsense\Quagga\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Quagga\BGP;
use \OPNsense\Core\Config;
use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Base\UIModelGrid;

/**
 *    Copyright (C) 2015 - 2017 Deciso B.V.
 *    Copyright (C) 2017 Fabian Franz
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
class BgpController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'BGP';
    static protected $internalModelClass = '\OPNsense\Quagga\BGP';
    public function getAction()
    {
        // define list of configurable settings
        $result = array();
        if ($this->request->isGet()) {
            $mdlBGP = new BGP();
            $result['bgp'] = $mdlBGP->getNodes();
        }
        return $result;
    }
    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            // load model and update with provided data
            $mdlBGP = new BGP();
            $mdlBGP->setNodes($this->request->getPost("bgp"));
            // perform validation
            $valMsgs = $mdlBGP->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["bgp.".$msg->getField()] = $msg->getMessage();
            }
            // serialize model to config and save
            if ($valMsgs->count() == 0) {
                $mdlBGP->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function searchNeighborAction()
    {
        $this->sessionClose();
        $mdlBGP = $this->getModel();
        $grid = new UIModelGrid($mdlBGP->neighbors->neighbor);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "address", "remoteas", "updatesource", "nexthopself", "defaultoriginate" )
        );
    }

    public function getNeighborAction($uuid = null)
    {
        $mdlBGP = $this->getModel();
        if ($uuid != null) {
            $node = $mdlBGP->getNodeByReference('neighbors.neighbor.' . $uuid);
            if ($node != null) {
                // return node
                return array("neighbor" => $node->getNodes());
            }
        } else {
            $node = $mdlBGP->neighbors->neighbor->add();
            return array("neighbor" => $node->getNodes());
        }
        return array();
    }

    public function addNeighborAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("neighbor")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlBGP = $this->getModel();
            $node = $mdlBGP->neighbors->neighbor->Add();
            $node->setNodes($this->request->getPost("neighbor"));
            $valMsgs = $mdlBGP->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "neighbor", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdlBGP->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function delNeighborAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlBGP = $this->getModel();
            if ($uuid != null) {
                if ($mdlBGP->neighbors->neighbor->del($uuid)) {
                    $mdlBGP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    public function setNeighborAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("neighbor")) {
            $mdlNeighbor = $this->getModel();
            if ($uuid != null) {
                $node = $mdlNeighbor->getNodeByReference('neighbors.neighbor.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $neighborInfo = $this->request->getPost("neighbor");
                    $node->setNodes($neighborInfo);
                    $valMsgs = $mdlNeighbor->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "neighbor", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }
                    if (count($result['validations']) == 0) {
                        // save config if validated correctly
                        $mdlNeighbor->serializeToConfig();
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
            $mdlNeighbor = $this->getModel();
            if ($uuid != null) {
                $node = $mdlNeighbor->getNodeByReference($elements . '.'. $element .'.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $result['result'] = "Disabled";
                        $node->enabled = "0";
                    } else {
                        $result['result'] = "Enabled";
                        $node->enabled = "1";
                    }
                    // if item has toggled, serialize to config and save
                    $mdlNeighbor->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    public function toggleNeighborAction($uuid)
    {
        return $this->toggle_handler($uuid, 'neighbors', 'neighbor');
    }
}
