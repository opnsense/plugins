<?php

/*
 * Copyright (C) 2017 David Harrigan
 * Copyright (C) 2017 Deciso B.V.
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

namespace OPNsense\Zerotier\Api;

require_once 'plugins.inc.d/zerotier.inc';

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Zerotier\Zerotier;

class NetworkController extends ApiMutableModelControllerBase
{

    protected static $internalModelName = 'Zerotier';
    protected static $internalModelClass = '\OPNsense\Zerotier\Zerotier';

    public function searchAction()
    {
        $this->sessionClose();
        $mdlZerotier = $this->getModel();
        $grid = new UIModelGrid($mdlZerotier->networks->network);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "networkId", "description")
        );
    }

    public function getAction($uuid = null)
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

    public function infoAction($uuid = null)
    {
        $mdlZerotier = $this->getModel();
        if ($uuid != null) {
            $network = $mdlZerotier->getNodeByReference('networks.network.' . $uuid);
            if ($network != null) {
                $networkId = $network->networkId->__toString();
                return array
                    (
                        "title" => gettext("Information on network") . " " . $networkId,
                        "message" => $this->listZerotierNetwork($networkId)
                    );
            }
        }
        return array();
    }

    public function setAction($uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("network")) {
            if ($uuid != null) {
                $mdlZerotier = $this->getModel();
                $network = $mdlZerotier->getNodeByReference('networks.network.' . $uuid);
                if ($network != null) {
                    $network->setNodes($this->request->getPost("network"));
                    $validationMessages = $mdlZerotier->performValidation();
                    foreach ($validationMessages as $field => $msg) {
                        if (!array_key_exists("validation", $result)) {
                            $result["validations"] = array();
                        }
                        $result["validation"]["network." . $msg->getField()] = $msg->getMessage();
                    }
                    if ($validationMessages->count() == 0) {
                        unset($result["validations"]);
                        $mdlZerotier->serializeToConfig();
                        Config::getInstance()->save();
                        $result["result"] = "saved";
                    }
                }
            }
        }
        return $result;
    }

    public function addAction()
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

    public function delAction($uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            if ($uuid != null) {
                $mdlZerotier = $this->getModel();
                if (!isEnabled($mdlZerotier)) {
                    $result["result"] = "service_not_enabled";
                    return $result;
                }
                $network = $mdlZerotier->getNodeByReference('networks.network.' . $uuid);
                if (isEnabled($network)) {
                    # Ensure we remove the interface before deleting the network
                    $this->toggleZerotierNetwork($network->networkId, 0);
                }
                if ($mdlZerotier->networks->network->del($uuid)) {
                    $mdlZerotier->serializeToConfig();
                    Config::getInstance()->save();
                    $result["result"] = "deleted";
                } else {
                    $result["result"] = "not_found";
                }
            }
        }
        return $result;
    }

    public function toggleAction($uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            if ($uuid != null) {
                $mdlZerotier = $this->getModel();
                if (!isEnabled($mdlZerotier)) {
                    $result["result"] = "service_not_enabled";
                    return $result;
                }
                $network = $mdlZerotier->getNodeByReference('networks.network.' . $uuid);
                if ($network != null) {
                    $networkId = $network->networkId;
                    if (isEnabled($network)) {
                        $network->enabled = "0";
                        $result['result'] = $this->toggleZerotierNetwork($networkId, 0);
                    } else {
                        $network->enabled = "1";
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
        $action = $enabled ? 'join' : 'leave';
        return trim((new Backend())->configdRun("zerotier $action $networkId"));
    }

    private function listZerotierNetwork($networkId)
    {
        $zerotierNetworks = trim((new Backend())->configdRun("zerotier listnetworks"));
        $zerotierNetworks = explode("200 listnetworks", $zerotierNetworks);
        foreach ($zerotierNetworks as $zerotierNetwork) {
            if (strpos($zerotierNetwork, $networkId) !== false) {
                return $zerotierNetwork;
            }
        }
        return gettext("Unable to obtain Zerotier information for network") . " " . $networkId . "! " . gettext("Is the network enabled?");
    }
}
