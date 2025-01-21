<?php

/*
 * Copyright (C) 2016 EURO-LOG AG
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

namespace OPNsense\FtpProxy\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;
use OPNsense\FtpProxy\FtpProxy;
use OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\FtpProxy
 */
class SettingsController extends ApiControllerBase
{
    /**
     * retrieve ftpproxy settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getProxyAction($uuid = null)
    {
        $mdlFtpProxy = new FtpProxy();
        if ($uuid != null) {
            $node = $mdlFtpProxy->getNodeByReference('ftpproxy.' . $uuid);
            if ($node != null) {
                // return node
                return array("ftpproxy" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlFtpProxy->ftpproxy->Add();
            return array("ftpproxy" => $node->getNodes());
        }
        return array();
    }

    /**
     * update ftpproxy with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setProxyAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("ftpproxy")) {
            $mdlFtpProxy = new FtpProxy();
            // keep a list to detect duplicates later
            $CurrentProxies =  $mdlFtpProxy->getNodes();
            if ($uuid != null) {
                $node = $mdlFtpProxy->getNodeByReference('ftpproxy.' . $uuid);
                if ($node != null) {
                    $Enabled = $node->enabled->__toString();
                    $result = array("result" => "failed", "validations" => array());
                    $proxyInfo = $this->request->getPost("ftpproxy");

                    $node->setNodes($proxyInfo);
                    $valMsgs = $mdlFtpProxy->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "ftpproxy", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }

                    if (count($result['validations']) == 0) {
                        // check for duplicates
                        foreach ($CurrentProxies['ftpproxy'] as $CurrentUUID => &$CurrentProxy) {
                            if (
                                $node->listenaddress->__toString() == $CurrentProxy['listenaddress'] &&
                                $node->listenport->__toString() == $CurrentProxy['listenport'] &&
                                $uuid != $CurrentUUID
                            ) {
                                return array(
                                          "result" => "failed",
                                          "validations" => array(
                                             "ftpproxy.listenaddress" => "Listen address in combination with Listen port already exists.",
                                             "ftpproxy.listenport" => "Listen port in combination with Listen address already exists."
                                          )
                                       );
                            }
                        }

                        // save config if validated correctly
                        $mdlFtpProxy->serializeToConfig();
                        Config::getInstance()->save();
                        // reload config
                        $svcFtpProxy = new ServiceController();
                        $result = $svcFtpProxy->reloadAction();
                    }
                }
            }
        }
        return $result;
    }

    /**
     * add new ftpproxy and set with attributes from post
     * @return array
     */
    public function addProxyAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("ftpproxy")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlFtpProxy = new FtpProxy();
            // keep a list to detect duplicates later
            $CurrentProxies =  $mdlFtpProxy->getNodes();
            $node = $mdlFtpProxy->ftpproxy->Add();
            $node->setNodes($this->request->getPost("ftpproxy"));

            $valMsgs = $mdlFtpProxy->performValidation();

            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "ftpproxy", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }

            if (count($result['validations']) == 0) {
                foreach ($CurrentProxies['ftpproxy'] as &$CurrentProxy) {
                    if (
                        $node->listenaddress->__toString() == $CurrentProxy['listenaddress']
                            && $node->listenport->__toString() == $CurrentProxy['listenport']
                    ) {
                        return array(
                                  "result" => "failed",
                                  "validations" => array(
                                     "ftpproxy.listenaddress" => "Listen address in combination with Listen port already exists.",
                                     "ftpproxy.listenport" => "Listen port in combination with Listen address already exists."
                                   )
                               );
                    }
                }

                // save config if validated correctly
                $mdlFtpProxy->serializeToConfig();
                Config::getInstance()->save();
                // reload config
                $svcFtpProxy = new ServiceController();
                $result = $svcFtpProxy->reloadAction();
            }
        }
        return $result;
    }

    /**
     * delete ftpproxy by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delProxyAction($uuid)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlFtpProxy = new FtpProxy();
            if ($uuid != null) {
                $node = $mdlFtpProxy->getNodeByReference('ftpproxy.' . $uuid);
                if ($node != null) {
                    if ($mdlFtpProxy->ftpproxy->del($uuid) == true) {
                        // if item is removed, serialize to config and save
                        $mdlFtpProxy->serializeToConfig();
                        Config::getInstance()->save();
                        // reload config
                        $svcFtpProxy = new ServiceController();
                        $result = $svcFtpProxy->reloadAction();
                    }
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * toggle ftpproxy by uuid (enable/disable)
     * @param $uuid item unique id
     * @return array status
     */
    public function toggleProxyAction($uuid)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlFtpProxy = new FtpProxy();
            if ($uuid != null) {
                $node = $mdlFtpProxy->getNodeByReference('ftpproxy.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    // if item has toggled, serialize to config and save
                    $mdlFtpProxy->serializeToConfig();
                    Config::getInstance()->save();
                    // reload config
                    $svcFtpProxy = new ServiceController();
                    $result = $svcFtpProxy->reloadAction();
                }
            }
        }
        return $result;
    }

    /**
     *
     * search ftpproxy
     * @return array
     */
    public function searchProxyAction()
    {
        $fields = array(
                "enabled",
                "listenaddress",
                "listenport",
                "sourceaddress",
                "rewritesourceport",
                "idletimeout",
                "maxsessions",
                "reverseaddress",
                "reverseport",
                "logconnections",
                "debuglevel",
                "description"
        );
        $mdlFtpProxy = new FtpProxy();

        $grid = new UIModelGrid($mdlFtpProxy->ftpproxy);
        $response = $grid->fetchBindRequest(
            $this->request,
            $fields,
            "listenport"
        );
        $svcFtpProxy = new ServiceController();
        foreach ($response['rows'] as &$row) {
            $result = $svcFtpProxy->statusAction($row['uuid']);
            if ($result['result'] == 'OK') {
                $row['status'] = 0;
                continue;
            }
            $row['status'] = 2;
        }

        return $response;
    }
}
