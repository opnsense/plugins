<?php

/*
 * Copyright (C) 2025 OPNsense Community
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

namespace OPNsense\Xproxy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class ServersController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'xproxy';
    protected static $internalModelClass = 'OPNsense\Xproxy\Xproxy';

    public function searchItemAction()
    {
        return $this->searchBase(
            "servers.server",
            array('description', 'protocol', 'address', 'port', 'security'),
            "description"
        );
    }

    public function setItemAction($uuid)
    {
        return $this->setBase("server", "servers.server", $uuid);
    }

    public function addItemAction()
    {
        $result = $this->addBase("server", "servers.server");
        if (isset($result['uuid']) && isset($result['result']) && $result['result'] === 'saved') {
            $mdl = $this->getModel();
            if (empty((string)$mdl->general->active_server)) {
                $mdl->general->active_server = $result['uuid'];
                $mdl->serializeToConfig();
                \OPNsense\Core\Config::getInstance()->save();
                $node = $mdl->getNodeByReference("servers.server." . $result['uuid']);
                if ($node !== null) {
                    $result['auto_selected'] = (string)$node->description;
                }
            }
        }
        return $result;
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase("server", "servers.server", $uuid);
    }

    public function delItemAction($uuid)
    {
        $mdl = $this->getModel();
        $wasActive = ((string)$mdl->general->active_server === $uuid);
        if ($wasActive) {
            $mdl->general->active_server = '';
            $mdl->serializeToConfig();
            \OPNsense\Core\Config::getInstance()->save();
        }
        $result = $this->delBase("servers.server", $uuid);
        if ($wasActive && isset($result['result']) && $result['result'] === 'deleted') {
            $mdl = $this->getModel();
            foreach ($mdl->servers->server->iterateItems() as $remainingUuid => $item) {
                $mdl->general->active_server = $remainingUuid;
                $mdl->serializeToConfig();
                \OPNsense\Core\Config::getInstance()->save();
                $result['auto_selected'] = (string)$item->description;
                break;
            }
        }
        return $result;
    }

    /**
     * Set a server as the active outbound proxy.
     */
    public function setActiveAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            if (!is_string($uuid) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $uuid)) {
                return $result;
            }
            $mdl = $this->getModel();
            $node = $mdl->getNodeByReference("servers.server." . $uuid);
            if ($node != null) {
                $mdl->general->active_server = $uuid;
                $valMsgs = $mdl->performValidation();
                if (count($valMsgs) == 0) {
                    $mdl->serializeToConfig();
                    \OPNsense\Core\Config::getInstance()->save();
                    $result["result"] = "saved";
                } else {
                    $result["validations"] = array();
                    foreach ($valMsgs as $msg) {
                        $result["validations"][$msg->getField()] = $msg->getMessage();
                    }
                }
            }
        }
        return $result;
    }
}
