<?php

/*
 * Copyright (C) 2020 Martin Wasley
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

namespace OPNsense\UDPBroadcastRelay\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;
use OPNsense\UDPBroadcastRelay\UDPBroadcastRelay;
use OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController Handles settings related API actions for the UDPBroadcastRelay
 * @package OPNsense\UDPBroadcastRelay
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'udpbroadcastrelay';
    protected static $internalModelClass = '\OPNsense\UDPBroadcastRelay\UDPBroadcastRelay;';
    protected static $internalModelUseSafeDelete = true;


/**
 * Class SettingsController
 * @package OPNsense\UDPBroadcastRelay
 */

    /**
     * retrieve udpbroadcastrelay settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getRelayAction($uuid = null)
    {
        $mdlUDPBroadcastRelay = new UDPBroadcastRelay();
        if ($uuid != null) {
            $node = $mdlUDPBroadcastRelay->getNodeByReference('udpbroadcastrelay.' . $uuid);
            if ($node != null) {
                // return node
                return array("udpbroadcastrelay" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlUDPBroadcastRelay->udpbroadcastrelay->Add();
            return array("udpbroadcastrelay" => $node->getNodes());
        }
        return array();
    }

    /**
     * update udpbroadcastrelay with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setRelayAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("udpbroadcastrelay")) {
            $mdlUDPBroadcastRelay = new UDPBroadcastRelay();
            // keep a list to detect duplicates later
            $CurrentProxies =  $mdlUDPBroadcastRelay->getNodes();
            if ($uuid != null) {
                $node = $mdlUDPBroadcastRelay->getNodeByReference('udpbroadcastrelay.' . $uuid);
                if ($node != null) {
                    $Enabled = $node->enabled->__toString();
                    $result = array("result" => "failed", "validations" => array());
                    $relayInfo = $this->request->getPost("udpbroadcastrelay");

                    $node->setNodes($relayInfo);
                    $valMsgs = $mdlUDPBroadcastRelay->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "udpbroadcastrelay", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }

                    if (count($result['validations']) == 0) {
                        // check for duplicates

                        foreach ($CurrentProxies['udpbroadcastrelay'] as $CurrentUUID => &$CurrentRelay) {
                            if (
                                $node->InstanceID->__toString() == $CurrentRelay['InstanceID'] &&
                                $node->listenport->__toString() == $CurrentRelay['listenport'] &&
                                $uuid != $CurrentUUID
                            ) {
                                return array(
                                          "result" => "failed",
                                          "validations" => array(
                                             "udpbroadcastrelay.InstanceID" => "Instance ID already in use.",
                                             "udpbroadcastrelay.listenport" => "Listen port already in use."
                                          )
                                       );
                            }
                            if (
                                $node->listenport->__toString() == $CurrentRelay['listenport'] &&
                                $uuid != $CurrentUUID
                            ) {
                                return array(
                                          "result" => "failed",
                                          "validations" => array(
                                             "udpbroadcastrelay.listenport" => "Listen Port already in use."
                                           )
                                       );
                            }
                            if (
                                $node->InstanceID->__toString() == $CurrentRelay['InstanceID'] &&
                                $uuid != $CurrentUUID
                            ) {
                                return array(
                                          "result" => "failed",
                                          "validations" => array(
                                             "udpbroadcastrelay.InstanceID" => "Instance ID already In use."
                                           )
                                       );
                            }
                            $result = count(explode(',', $node->interfaces));
                            if (
                                $result < 2 &&
                                $uuid != $CurrentUUID
                            ) {
                                return array(
                                          "result" => "failed",
                                          "validations" => array(
                                             "udpbroadcastrelay.interfaces" => "At least two interfaces must be selected."
                                           )
                                       );
                            }
                        }

                        // save config if validated correctly
                        $mdlUDPBroadcastRelay->serializeToConfig();
                        Config::getInstance()->save();
                        // reload config
                        $svcUDPBroadcastRelay = new ServiceController();
                        $result = $svcUDPBroadcastRelay->reloadAction();
                    }
                }
            }
        }
        return $result;
    }

    /**
     * add new udpbroadcastrelay and set with attributes from post
     * @return array
     */
    public function addRelayAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("udpbroadcastrelay")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlUDPBroadcastRelay = new UDPBroadcastRelay();
            // keep a list to detect duplicates later
            $CurrentProxies =  $mdlUDPBroadcastRelay->getNodes();
            $node = $mdlUDPBroadcastRelay->udpbroadcastrelay->Add();
            $node->setNodes($this->request->getPost("udpbroadcastrelay"));

            $valMsgs = $mdlUDPBroadcastRelay->performValidation();

            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "udpbroadcastrelay", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }

            if (count($result['validations']) == 0) {
                foreach ($CurrentProxies['udpbroadcastrelay'] as &$CurrentRelay) {
                    if (
                            $node->InstanceID->__toString() == $CurrentRelay['InstanceID'] &&
                            $node->listenport->__toString() == $CurrentRelay['listenport']
                    ) {
                            return array(
                                      "result" => "failed",
                                      "validations" => array(
                                         "udpbroadcastrelay.InstanceID" => "Instance ID already in use.",
                                         "udpbroadcastrelay.listenport" => "Listen port already in use."
                                      )
                                );
                    }
                    if (
                        $node->listenport->__toString() == $CurrentRelay['listenport']
                    ) {
                        return array(
                                  "result" => "failed",
                                  "validations" => array(
                                     "udpbroadcastrelay.listenport" => "Listen Port already in use."
                                   )
                               );
                    }
                    if (
                        $node->InstanceID->__toString() == $CurrentRelay['InstanceID']
                    ) {
                        return array(
                                  "result" => "failed",
                                  "validations" => array(
                                     "udpbroadcastrelay.InstanceID" => "Instance ID already In use."
                                   )
                               );
                    }

                    $result = count(explode(',', $node->interfaces));
                    if (
                        $result < 2
                    ) {
                        return array(
                                  "result" => "failed",
                                  "validations" => array(
                                     "udpbroadcastrelay.interfaces" => "At least two interfaces must be selected."
                                   )
                               );
                    }
                }

                // save config if validated correctly
                $mdlUDPBroadcastRelay->serializeToConfig();
                Config::getInstance()->save();
                // reload config
                $svcUDPBroadcastRelay = new ServiceController();
                $result = $svcUDPBroadcastRelay->reloadAction();
            }
        }
        return $result;
    }

    /**
     * delete udpbroadcastrelay by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delRelayAction($uuid)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlUDPBroadcastRelay = new UDPBroadcastRelay();
            if ($uuid != null) {
                $node = $mdlUDPBroadcastRelay->getNodeByReference('udpbroadcastrelay.' . $uuid);
                if ($node != null) {
                    if ($mdlUDPBroadcastRelay->udpbroadcastrelay->del($uuid) == true) {
                        // if item is removed, serialize to config and save
                        $mdlUDPBroadcastRelay->serializeToConfig();
                        Config::getInstance()->save();
                        // reload config
                        $svcUDPBroadcastRelay = new ServiceController();
                        $result = $svcUDPBroadcastRelay->reloadAction();
                    }
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * toggle udpbroadcastrelay by uuid (enable/disable)
     * @param $uuid item unique id
     * @return array status
     */
    public function toggleRelayAction($uuid)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlUDPBroadcastRelay = new UDPBroadcastRelay();
            if ($uuid != null) {
                $node = $mdlUDPBroadcastRelay->getNodeByReference('udpbroadcastrelay.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    // if item has toggled, serialize to config and save
                    $mdlUDPBroadcastRelay->serializeToConfig();
                    Config::getInstance()->save();
                    // reload config
                    $svcUDPBroadcastRelay = new ServiceController();
                    $result = $svcUDPBroadcastRelay->reloadAction();
                }
            }
        }
        return $result;
    }

    /**
     *
     * search udpbroadcastrelay
     * @return array
     */
    public function searchRelayAction()
    {
        $fields = array(
                "enabled",
                "interfaces",
                "multicastaddress",
                "sourceaddress",
                "listenport",
                "InstanceID",
                "RevertTTL",
                "description"
        );
        $mdlUDPBroadcastRelay = new UDPBroadcastRelay();

        $grid = new UIModelGrid($mdlUDPBroadcastRelay->udpbroadcastrelay);
        $response = $grid->fetchBindRequest(
            $this->request,
            $fields,
            "InstanceID"
        );
        $svcUDPBroadcastRelay = new ServiceController();
        foreach ($response['rows'] as &$row) {
            $result = $svcUDPBroadcastRelay->statusAction($row['uuid']);
            if ($result['result'] == 'OK') {
                $row['status'] = 0;
                continue;
            }
            $node = $mdlUDPBroadcastRelay->getNodeByReference('udpbroadcastrelay.' . $row['uuid']);
            if ($node != null) {
                if ($node->enabled->__toString() == "1") {
                    $row['status'] = 2;
                } else {
                    $row['status'] = 5;
                }
            }
        }

        return $response;
    }
}
