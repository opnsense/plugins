<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

namespace OPNsense\Tinc\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UIModelGrid;
use OPNsense\Core\Backend;

/**
 * Class SettingsController Handles settings related API actions for Tinc VPN
 * @package OPNsense\Tinc
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'tinc';
    protected static $internalModelClass = '\OPNsense\Tinc\Tinc';

    /**
     * get network action
     * @param string $uuid item unique id
     * @return array
     */
    public function getNetworkAction($uuid = null)
    {
        if ($uuid == null) {
            // generate new node, but don't save to disc
            $node = $this->getModel()->networks->network->Add();
            return array("network" => $node->getNodes());
        } else {
            $node = $this->getModel()->getNodeByReference('networks.network.' . $uuid);
            if ($node != null) {
                // return node
                return array("network" => $node->getNodes());
            }
        }
        return array();
    }

    /**
     * set network action
     * @param string $uuid item unique id
     * @return array
     */
    public function setNetworkAction($uuid = null)
    {
        if ($this->request->isPost() && $this->request->hasPost("network")) {
            if ($uuid != null) {
                $node = $this->getModel()->getNodeByReference('networks.network.' . $uuid);
            } else {
                $node = $this->getModel()->networks->network->Add();
            }
            $node->setNodes($this->request->getPost("network"));
            if (empty((string)$node->pubkey) || empty((string)$node->privkey)) {
                // generate new keypair
                $backend = new Backend();
                $keys = json_decode(trim($backend->configdRun("tinc gen-key")), true);
                $node->pubkey = (string)$keys['pub'];
                $node->privkey = $keys['priv'];
            }
            return $this->validateAndSave($node, 'network');
        }
        return array("result" => "failed");
    }


    /**
     * search user defined rules
     * @return array list of found user rules
     */
    public function searchNetworkAction()
    {
        $grid = new UIModelGrid($this->getModel()->networks->network);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name"),
            "name"
        );
    }

    /**
     * del network action
     * @param string $uuid item unique id
     * @return array
     */
    public function delNetworkAction($uuid)
    {
        $result = array('result' => 'failed');
        if ($this->request->isPost()) {
            if ($this->getModel()->networks->network->del($uuid)) {
                $result = $this->validateAndSave();
            }
        }
        return $result;
    }

    /**
     * toggle network item action
     * @param string $uuid item unique id
     * @param boolean $enabled
     * @return array
     */
    public function toggleNetworkAction($uuid, $enabled = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            if ($uuid != null) {
                $node = $this->getModel()->getNodeByReference('networks.network.' . $uuid);
                if ($node != null) {
                    if ($enabled == "0" || $enabled == "1") {
                        $node->enabled = (string)$enabled;
                    } elseif ((string)$node->enabled == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $result['result'] = $node->enabled;
                    $this->save();
                }
            }
        }
        return $result;
    }

    /**
     * get host action
     * @param string $uuid item unique id
     * @return array
     */
    public function getHostAction($uuid = null)
    {
        if ($uuid == null) {
            // generate new node, but don't save to disc
            $node = $this->getModel()->hosts->host->Add();
            return array("host" => $node->getNodes());
        } else {
            $node = $this->getModel()->getNodeByReference('hosts.host.' . $uuid);
            if ($node != null) {
                // return node
                return array("host" => $node->getNodes());
            }
        }
        return array();
    }

    /**
     * set host action
     * @param string $uuid item unique id
     * @return array
     */
    public function setHostAction($uuid = null)
    {
        if ($this->request->isPost() && $this->request->hasPost("host")) {
            if ($uuid != null) {
                $node = $this->getModel()->getNodeByReference('hosts.host.' . $uuid);
            } else {
                $node = $this->getModel()->hosts->host->Add();
            }
            $node->setNodes($this->request->getPost("host"));
            return $this->validateAndSave($node, 'host');
        }
        return array("result" => "failed");
    }


    /**
     * search user defined rules
     * @return array list of found user rules
     */
    public function searchHostAction()
    {
        $grid = new UIModelGrid($this->getModel()->hosts->host);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "hostname", 'network'),
            "name"
        );
    }

    /**
     * del host action
     * @param string $uuid item unique id
     * @return array
     */
    public function delHostAction($uuid)
    {
        $result = array('result' => 'failed');
        if ($this->request->isPost()) {
            if ($this->getModel()->hosts->host->del($uuid)) {
                $result = $this->validateAndSave();
            }
        }
        return $result;
    }

    /**
     * toggle host item action
     * @param string $uuid item unique id
     * @param boolean $enabled
     * @return array
     */
    public function toggleHostAction($uuid, $enabled = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            if ($uuid != null) {
                $node = $this->getModel()->getNodeByReference('hosts.host.' . $uuid);
                if ($node != null) {
                    if ($enabled == "0" || $enabled == "1") {
                        $node->enabled = (string)$enabled;
                    } elseif ((string)$node->enabled == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $result['result'] = $node->enabled;
                    $this->save();
                }
            }
        }
        return $result;
    }
}
