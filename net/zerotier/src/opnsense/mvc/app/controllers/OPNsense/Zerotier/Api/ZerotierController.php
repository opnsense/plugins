<?php
/**
 *    Copyright (C) 2017 David Harrigan
 *    Copyright (C) 2017 Deciso B.V.
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
namespace OPNsense\Zerotier\Api;

use \OPNsense\Core\Config;
use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Base\UIModelGrid;
use \OPNsense\Zerotier\Zerotier;

class ZerotierController extends ApiMutableModelControllerBase
{

    static protected $internalModelName = 'Zerotier';
    static protected $internalModelClass = '\OPNsense\Zerotier\Zerotier';

    public function getAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdlZerotier = new Zerotier();
            $result['zerotier'] = $mdlZerotier->getNodes();
        }
        return $result;
    }

    public function searchNetworkAction()
    {
        $this->sessionClose();
        $mdlZerotier = $this->getModel();
        $grid = new UIModelGrid($mdlZerotier->networks->network);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "networkId", "description")
        );
    }

    public function getNetworkAction($uuid = null)
    {
        $mdlZerotier = $this->getModel();
        if ($uuid != null) {
            $network = $mdlZerotier->getNodeByReference('networks.network.' . $uuid);
            if ($network != null) {
                return array("network" => $network->getNodes());
            }
        } else {
            $network = $mdlZerotier->networks->network->add();
            return array("network" => $network->getNodes());
        }
        return array();
    }

    public function setNetworkAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlZerotier = new Zerotier();
            $mdlZerotier->setNodes($this->request->getPost("network"));
            $validationMessages = $mdlZerotier->performValidation();
            foreach ($validationMessages as $field => $msg) {
                if(!array_key_exists("validation", $result)) {
                    $result["validations"] = array();
                }
                $result["validation"]["network.".$msg->getField()] = $msg->getMessage();
            }
            if($validationMessages->count() == 0) {
                unset($result["validations"]);
                $mdlZerotier->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function addNetworkAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("network")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlZerotier = $this->getModel();
            $network = $mdlZerotier->networks->network->add();
            $network->setNodes($this->request->getPost("network"));
            $validationMessages = $mdlZerotier->performValidation();
            foreach ($validationMessages as $field => $msg) {
                $fieldName = str_replace($network->__reference, "network", $msg->getField());
                $result["validations"][$fieldName] = $msg->getMessage();
            }
            if ($validationMessages->count() == 0) {
                unset($result["validations"]);
                $mdlZerotier->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }

        }
        return $result;
    }

    public function delNetworkAction($uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlZerotier = $this->getModel();
            if ($uuid != null) {
                if ($mdlZerotier->networks->network->del($uuid)) {
                    $mdlZerotier->serializeToConfig();
                    Config::getInstance()->save();
                    $result["result"] = "deleted";
                } else {
                    $result["result"] = "not found";
                }
            }
        }
        return $result;
    }

    public function toggleNetworkAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlZerotier = $this->getModel();
            if ($uuid != null) {
                $node = $mdlZerotier->getNodeByReference('networks.network.' . $uuid);
                if ($node != null) {
                    $networkId = $node->networkId;
                    if ($node->enabled->__toString() == "1") {
                        $node->enabled = "0";
                        $result['result'] = $this->toggleZerotierNetwork($networkId, 0);
                    } else {
                        $node->enabled = "1";
                        $result['result'] = $this->toggleZerotierNetwork($networkId, 1);
                    }
                    $mdlZerotier->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    private function toggleZerotierNetwork($networkId, $enabled)
    {
        $backend = new Backend();
        return trim($backend->configdRun("zerotier ".($enabled ? "join " : "leave ").$networkId));
    }

}
