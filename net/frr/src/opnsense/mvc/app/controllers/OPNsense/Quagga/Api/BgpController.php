<?php
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

namespace OPNsense\Quagga\Api;

use \OPNsense\Quagga\BGP;
use \OPNsense\Core\Config;
use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Base\UIModelGrid;

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
            array("enabled", "address", "remoteas", "updatesource", "nexthopself", "defaultoriginate", "linkedPrefixlistIn", "linkedPrefixlistOut", "linkedRoutemapIn", "linkedRoutemapOut" )
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
                unset($result['validations']);
                // save config if validated correctly
                $mdlBGP->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
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

    public function searchAspathAction()
    {
        $this->sessionClose();
        $mdlBGP = $this->getModel();
        $grid = new UIModelGrid($mdlBGP->aspaths->aspath);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "number", "action", "as" )
        );
    }

    public function getAspathAction($uuid = null)
    {
        $mdlBGP = $this->getModel();
        if ($uuid != null) {
            $node = $mdlBGP->getNodeByReference('aspaths.aspath.' . $uuid);
            if ($node != null) {
                // return node
                return array("aspath" => $node->getNodes());
            }
        } else {
            $node = $mdlBGP->aspaths->aspath->add();
            return array("aspath" => $node->getNodes());
        }
        return array();
    }

    public function addAspathAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("aspath")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlBGP = $this->getModel();
            $node = $mdlBGP->aspaths->aspath->Add();
            $node->setNodes($this->request->getPost("aspath"));
            $valMsgs = $mdlBGP->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "aspath", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdlBGP->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function delAspathAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlBGP = $this->getModel();
            if ($uuid != null) {
                if ($mdlBGP->aspaths->aspath->del($uuid)) {
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

    public function setAspathAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("aspath")) {
            $mdlNeighbor = $this->getModel();
            if ($uuid != null) {
                $node = $mdlNeighbor->getNodeByReference('aspaths.aspath.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $aspathInfo = $this->request->getPost("aspath");
                    $node->setNodes($aspathInfo);
                    $valMsgs = $mdlNeighbor->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "aspath", $msg->getField());
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

    public function searchPrefixlistAction()
    {
        $this->sessionClose();
        $mdlBGP = $this->getModel();
        $grid = new UIModelGrid($mdlBGP->prefixlists->prefixlist);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "seqnumber", "action", "network" )
        );
    }
    public function getPrefixlistAction($uuid = null)
    {
        $mdlBGP = $this->getModel();
        if ($uuid != null) {
            $node = $mdlBGP->getNodeByReference('prefixlists.prefixlist.' . $uuid);
            if ($node != null) {
                // return node
                return array("prefixlist" => $node->getNodes());
            }
        } else {
            $node = $mdlBGP->prefixlists->prefixlist->add();
            return array("prefixlist" => $node->getNodes());
        }
        return array();
    }
    public function addPrefixlistAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("prefixlist")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlBGP = $this->getModel();
            $node = $mdlBGP->prefixlists->prefixlist->Add();
            $node->setNodes($this->request->getPost("prefixlist"));
            $valMsgs = $mdlBGP->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "prefixlist", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdlBGP->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result["result"] = "saved";
            }
        }
        return $result;
    }
    public function delPrefixlistAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlBGP = $this->getModel();
            if ($uuid != null) {
                if ($mdlBGP->prefixlists->prefixlist->del($uuid)) {
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
    public function setPrefixlistAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("prefixlist")) {
            $mdlNeighbor = $this->getModel();
            if ($uuid != null) {
                $node = $mdlNeighbor->getNodeByReference('prefixlists.prefixlist.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $prefixlistInfo = $this->request->getPost("prefixlist");
                    $node->setNodes($prefixlistInfo);
                    $valMsgs = $mdlNeighbor->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "prefixlist", $msg->getField());
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

    public function searchRoutemapAction()
    {
        $this->sessionClose();
        $mdlBGP = $this->getModel();
        $grid = new UIModelGrid($mdlBGP->routemaps->routemap);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "action", "id", "match", "set" )
        );
    }

    public function getRoutemapAction($uuid = null)
    {
        $mdlBGP = $this->getModel();
        if ($uuid != null) {
            $node = $mdlBGP->getNodeByReference('routemaps.routemap.' . $uuid);
            if ($node != null) {
                // return node
                return array("routemap" => $node->getNodes());
            }
        } else {
            $node = $mdlBGP->routemaps->routemap->add();
            return array("routemap" => $node->getNodes());
        }
        return array();
    }

    public function addRoutemapAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("routemap")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlBGP = $this->getModel();
            $node = $mdlBGP->routemaps->routemap->Add();
            $node->setNodes($this->request->getPost("routemap"));
            $valMsgs = $mdlBGP->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "routemap", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdlBGP->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    public function delRoutemapAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlBGP = $this->getModel();
            if ($uuid != null) {
                if ($mdlBGP->routemaps->routemap->del($uuid)) {
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

    public function setRoutemapAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("routemap")) {
            $mdlNeighbor = $this->getModel();
            if ($uuid != null) {
                $node = $mdlNeighbor->getNodeByReference('routemaps.routemap.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $routemapInfo = $this->request->getPost("routemap");
                    $node->setNodes($routemapInfo);
                    $valMsgs = $mdlNeighbor->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "routemap", $msg->getField());
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

    public function toggleAspathAction($uuid)
    {
        return $this->toggle_handler($uuid, 'aspaths', 'aspath');
    }

    public function togglePrefixlistAction($uuid)
    {
        return $this->toggle_handler($uuid, 'prefixlists', 'prefixlist');
    }

    public function toggleRoutemapAction($uuid)
    {
        return $this->toggle_handler($uuid, 'routemaps', 'routemap');
    }
}
