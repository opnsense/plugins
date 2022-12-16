<?php

/**
 *    Copyright (C) 2016-2017 Frank Wall
 *    Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\HAProxy;

use OPNsense\Base\BaseModel;

/**
 * Class HAProxy
 * @package OPNsense\HAProxy
 */
class HAProxy extends BaseModel
{
    /**
     * check if module is enabled
     * @param $checkFrontends bool enable in-depth check (1 or more active frontends)
     * @return bool is the HAProxy service enabled
     */
    public function isEnabled($checkFrontends = true)
    {
        if ((string)$this->general->enabled === "1") {
            if ($checkFrontends === true) {
                foreach ($this->frontends->frontend->iterateItems() as $frontend) {
                    if ((string)$frontend->enabled === "1") {
                        return true; // Found a active frontend
                    }
                }
            } else {
                return true; // HAProxy enabled
            }
        }
        return false;
    }

    /**
     * retrieve frontend by number
     * @param $uuid frontend number
     * @return null|BaseField frontend details
     */
    public function getByFrontendID($uuid)
    {
        foreach ($this->frontends->frontend->iterateItems() as $frontend) {
            if ((string)$uuid === (string)$frontend->getAttributes()["uuid"]) {
                return $frontend;
            }
        }
        return null;
    }

    /**
     * retrieve backend by number
     * @param $uuid backend number
     * @return null|BaseField backend details
     */
    public function getByBackendID($uuid)
    {
        foreach ($this->backends->backend->iterateItems() as $backend) {
            if ((string)$uuid === (string)$backend->getAttributes()["uuid"]) {
                return $backend;
            }
        }
        return null;
    }

    /**
     * retrieve server by number
     * @param $uuid server number
     * @return null|BaseField server details
     */
    public function getByServerID($uuid)
    {
        foreach ($this->servers->server->iterateItems() as $server) {
            if ((string)$uuid === (string)$server->getAttributes()["uuid"]) {
                return $server;
            }
        }
        return null;
    }

    /**
     * retrieve action by number
     * @param $uuid action number
     * @return null|BaseField action details
     */
    public function getByActionID($uuid)
    {
        foreach ($this->actions->action->iterateItems() as $action) {
            if ((string)$uuid === (string)$action->getAttributes()["uuid"]) {
                return $action;
            }
        }
        return null;
    }

    /**
     * retrieve ACL by number
     * @param $uuid ACL number
     * @return null|BaseField ACL details
     */
    public function getByAclID($uuid)
    {
        foreach ($this->acls->acl->iterateItems() as $acl) {
            if ((string)$uuid === (string)$acl->getAttributes()["uuid"]) {
                return $acl;
            }
        }
        return null;
    }

    /**
     * create a new ACL
     * @param string $name
     * @param string $expression
     * @param string $description
     * @param string $negate
     * @param hash $parameters
     * @return string
     */
    public function newAcl($name, $expression, $description = "", $negate = "0", $parameters = array())
    {
        $acl = $this->acls->acl->Add();
        $uuid = $acl->getAttributes()['uuid'];
        $acl->name = $name;
        $acl->expression = $expression;
        $acl->description = $description;
        $acl->negate = $negate;
        foreach ($parameters as $key => $value) {
            $acl->$key = $value;
        }
        return $uuid;
    }

    /**
     * create a new action
     * @param string $name
     * @param string $testType
     * @param string $type
     * @param string $description
     * @param string $linkedAcls
     * @param string $operator
     * @param string $useBackend
     * @param string $useServer
     * @param string $actionName
     * @param string $actionFind
     * @param string $actionValue
     * @return string
     */
    public function newAction($name, $testType, $type, $description = "", $linkedAcls = "", $operator = "and", $parameters = array())
    {
        $action = $this->actions->action->Add();
        $uuid = $action->getAttributes()['uuid'];
        $action->name = $name;
        $action->testType = $testType;
        $action->type = $type;
        $action->description = $description;
        $action->linkedAcls = $linkedAcls;
        $action->operator = $operator;
        foreach ($parameters as $key => $value) {
            $action->$key = $value;
        }
        return $uuid;
    }

    /**
     * create a new server
     * @param string $name
     * @param string $address
     * @param string $port
     * @param string $mode
     * @param string $description
     * @param string $ssl
     * @param string $sslVerify
     * @param string $weight
     * @return string
     */
    public function newServer($name, $address, $port, $mode, $description = "", $ssl = "0", $sslVerify = "1", $weight = "")
    {
        $srv = $this->servers->server->Add();
        $uuid = $srv->getAttributes()['uuid'];
        $srv->name = $name;
        $srv->address = $address;
        $srv->port = $port;
        $srv->mode = $mode;
        $srv->description = $description;
        $srv->ssl = $ssl;
        $srv->sslVerify = $sslVerify;
        $srv->weight = $weight;
        return $uuid;
    }

    /**
     * create a new backend
     * @param string $name
     * @param string $mode
     * @param string $algorithm
     * @param string $enabled
     * @param string $description
     * @param string $linkedServers
     * @param string $linkedActions
     * @return string
     */
    public function newBackend($name, $mode, $algorithm, $enabled = "0", $description = "", $linkedServers = "", $linkedActions = "")
    {
        $backend = $this->backends->backend->Add();
        $uuid = $backend->getAttributes()['uuid'];
        $backend->name = $name;
        $backend->mode = $mode;
        $backend->algorithm = $algorithm;
        $backend->enabled = $enabled;
        $backend->description = $description;
        $backend->linkedServers = $linkedServers;
        $backend->linkedActions = $linkedActions;
        return $uuid;
    }

    /**
     * link an ACL to an action
     * @param string $acl_uuid
     * @param string $action_uuid
     * @return string
     */
    public function linkAclToAction($acl_uuid, $action_uuid, $replace = false)
    {
        // ACL must exist
        $acl = $this->getByAclID($acl_uuid);
        if ((string)$acl === false) {
            return;
        }

        // Action must exist
        $action = $this->getByActionID($action_uuid);
        if ((string)$action === false) {
            return;
        }

        // Check if the ACL is already linked to the Action.
        $linkedAcls = (string)$action->linkedAcls;
        if (!empty($linkedAcls) and !($replace)) {
            if (strpos($linkedAcls, $acl_uuid) !== false) {
                // Match! Nothing to do.
                return $acl_uuid;
            } else {
                // Extend existing string.
                $linkedAcls .= ",${acl_uuid}";
            }
        } else {
            $linkedAcls = $acl_uuid;
        }

        // Add ACL
        $action->linkedAcls = $linkedAcls;

        return $acl_uuid;
    }

    /**
     * link a server to a backend
     * @param string $server_uuid
     * @param string $backend_uuid
     * @return string
     */
    public function linkServerToBackend($server_uuid, $backend_uuid, $replace = false)
    {
        // Server must exist
        $server = $this->getByServerID($server_uuid);
        if ((string)$server === false) {
            return;
        }

        // Backend must exist
        $backend = $this->getByBackendID($backend_uuid);
        if ((string)$backend === false) {
            return;
        }

        // Check if the server is already linked to the backend.
        $linkedServers = (string)$backend->linkedServers;
        if (!empty($linkedServers) and !($replace)) {
            if (strpos($linkedServers, $server_uuid) !== false) {
                // Match! Nothing to do.
                return $server_uuid;
            } else {
                // Extend existing string.
                $linkedServers .= ",${server_uuid}";
            }
        } else {
            $linkedServers = $server_uuid;
        }

        // Add server
        $backend->linkedServers = $linkedServers;

        return $server_uuid;
    }

    /**
     * link a action to a frontend
     * @param string $action_uuid
     * @param string $frontend_uuid
     * @return string
     */
    public function linkActionToFrontend($action_uuid, $frontend_uuid, $replace = false)
    {
        // Action must exist
        $action = $this->getByActionID($action_uuid);
        if ((string)$action === false) {
            return;
        }

        // Frontend must exist
        $frontend = $this->getByFrontendID($frontend_uuid);
        if ((string)$frontend === false) {
            return;
        }

        // Check if the action is already linked to the frontend.
        $linkedActions = (string)$frontend->linkedActions;
        if (!empty($linkedActions) and !($replace)) {
            if (strpos($linkedActions, $action_uuid) !== false) {
                // Match! Nothing to do.
                return $action_uuid;
            } else {
                // Extend existing string.
                $linkedActions .= ",${action_uuid}";
            }
        } else {
            $linkedActions = $action_uuid;
        }

        // Add action
        $frontend->linkedActions = $linkedActions;

        return $action_uuid;
    }
}
