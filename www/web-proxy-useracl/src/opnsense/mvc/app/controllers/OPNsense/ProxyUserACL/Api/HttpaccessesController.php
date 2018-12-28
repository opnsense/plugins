<?php

/**
 *    Copyright (C) 2019 Smart-Soft
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

namespace OPNsense\ProxyUserACL\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\Base\UIModelGrid;
use \OPNsense\ProxyUserACL\ProxyUserACL;
use \OPNsense\ProxyUserACL\Tools;

/**
 * Class HttpaccessesController
 * @package OPNsense\ProxyUserACL\Api
 */
class HttpaccessesController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'Httpaccess';
    static protected $internalModelClass = '\OPNsense\ProxyUserACL\ProxyUserACL';

    /**
     * search http_access list
     * @return array result
     * @throws \OPNsense\Base\ModelException
     */
    public function searchACLAction()
    {
        $this->sessionClose();

        $mdlProxyUserACL = new ProxyUserACL();
        $grid = new UIModelGrid($mdlProxyUserACL->general->HTTPAccesses->HTTPAccess);
        $columns = ["Domains", "Agents", "Times", "Mimes", "Srcs", "Dsts", "Arps"];
        $ret = $grid->fetchBindRequest($this->request,
            array_merge($columns, ["Users", "Black", "Priority", "uuid"]),
            "Priority");
        foreach ($ret["rows"] as &$row) {
            $visible = [];
            foreach ($columns as $column) {
                if ($row[$column] != "") {
                    $visible[] = $row[$column];
                }
            }

            $user_list = [];
            foreach (explode(",", $row["Users"]) as $user_id) {
                if ($user_id === "") {
                    continue;
                }
                foreach ($mdlProxyUserACL->general->Users->User->getChildren() as $user) {
                    if ($user->id->__toString() == $user_id) {
                        $user_list[] = $user->Description->__toString();
                    }
                }
            }

            if ($user_list != []) {
                $visible[] = implode(",", $user_list);
            }

            $row["Visible"] = implode("|", $visible);
        }
        return $ret;
    }

    /**
     * add new http_access and set with attributes from post
     * @return array result status
     * @throws \OPNsense\Base\ModelException
     * @throws \Phalcon\Validation\Exception
     */
    public function addACLAction()
    {
        if (!$this->request->isPost() || !$this->request->hasPost("Httpaccess")) {
            return ["result" => "failed"];
        }

        $result = ["result" => "failed", "validations" => []];
        $mdlProxyUserACL = new ProxyUserACL();
        $post = $this->request->getPost("Httpaccess");

        $count = count($mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->getNodes());
        if ($post["Priority"] > $count) {
            $post["Priority"] = $count;
        }
        foreach ($mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->sortedBy("Priority", true) as $acl) {
            $key = $acl->getAttributes()["uuid"];
            $priority = (string)$mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->{$key}->Priority;
            if ($priority < $post["Priority"]) {
                break;
            }
            $mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->{$key}->Priority = (string)($priority + 1);
        }
        $node = $mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->Add();
        $node->setNodes($post);
        $valMsgs = $mdlProxyUserACL->performValidation();

        foreach ($valMsgs as $field => $msg) {
            $fieldnm = str_replace($node->__reference, "Httpaccess", $msg->getField());
            $result["validations"][$fieldnm] = $msg->getMessage();
        }

        if (count($result['validations']) > 0) {
            return $result;
        }

        // save config if validated correctly
        $mdlProxyUserACL->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "saved"];
    }

    /**
     * retrieve http_access settings or return defaults
     * @param string $uuid item unique id
     * @return array result
     * @throws \OPNsense\Base\ModelException
     */
    public function getACLAction($uuid = null)
    {
        $mdlProxyUserACL = new ProxyUserACL();
        if ($uuid == null) {
            // generate new node, but don't save to disc
            $node = $mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->add();
            $nodes = $node->getNodes();
            foreach ($mdlProxyUserACL->general->Users->User->getChildren() as $uuid => $user) {
                $nodes["Users"][$user->id->__toString()] = [
                    "value" => $user->Description->__toString(),
                    "selected" => "0"
                ];
            }
            return ["Httpaccess" => $nodes];
        }

        $node = $mdlProxyUserACL->getNodeByReference('general.HTTPAccesses.HTTPAccess.' . $uuid);
        if ($node != null) {
            // return node
            $nodes = $node->getNodes();
            foreach ($mdlProxyUserACL->general->Users->User->getChildren() as $uuid => $user) {
                if (isset($nodes["Users"][$user->id->__toString()])) {
                    $nodes["Users"][$user->id->__toString()] = ["value" => $user->Description->__toString()];
                } else {
                    $nodes["Users"][$user->id->__toString()] = [
                        "value" => $user->Description->__toString(),
                        "selected" => "0"
                    ];
                }
            }
            return ["Httpaccess" => $nodes];
        }

        return [];
    }

    /**
     * update http_access item
     * @param string $uuid item unique id
     * @return array result status
     * @throws \OPNsense\Base\ModelException
     * @throws \Phalcon\Validation\Exception
     */
    public function setACLAction($uuid)
    {
        if (!$this->request->isPost() || !$this->request->hasPost("Httpaccess")) {
            return ["result" => "failed"];
        }

        $mdlProxyUserACL = new ProxyUserACL();
        if ($uuid == null) {
            return ["result" => "failed"];
        }

        $node = $mdlProxyUserACL->getNodeByReference('general.HTTPAccesses.HTTPAccess.' . $uuid);
        if ($node == null) {
            return ["result" => "failed"];
        }

        $result = ["result" => "failed", "validations" => []];
        $ACLInfo = $this->request->getPost("Httpaccess");
        $old_priority = (string)$node->Priority;
        $new_priority = $ACLInfo["Priority"];

        if ($new_priority < $old_priority) {
            if ($new_priority < 0) {
                $new_priority = 0;
            }

            foreach ($mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->sortedBy("Priority", true) as $acl) {
                $key = $acl->getAttributes()["uuid"];
                $priority = (string)$mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->{$key}->Priority;
                if ($priority < $new_priority) {
                    break;
                }
                if ($priority >= $old_priority) {
                    continue;
                }
                $mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->{$key}->Priority = (string)($priority + 1);
            }
        } elseif (($new_priority > $old_priority)) {
            $count = count($mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->getNodes());
            if ($new_priority >= $count) {
                $new_priority = $count - 1;
                $ACLInfo["Priority"] = (string)$new_priority;
            }
            foreach ($mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->sortedBy("Priority") as $acl) {
                $key = $acl->getAttributes()["uuid"];
                $priority = (string)$mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->{$key}->Priority;
                if ($priority > $new_priority) {
                    break;
                }
                if ($priority <= $old_priority) {
                    continue;
                }
                $mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->{$key}->Priority = (string)($priority - 1);
            }
        }
        $node->setNodes($ACLInfo);
        $valMsgs = $mdlProxyUserACL->performValidation();
        foreach ($valMsgs as $field => $msg) {
            $fieldnm = str_replace($node->__reference, "Httpaccess", $msg->getField());
            $result["validations"][$fieldnm] = $msg->getMessage();
        }

        if (count($result['validations']) > 0) {
            return $result;
        }

        // save config if validated correctly
        $mdlProxyUserACL->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "saved"];
    }

    /**
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function delACLAction($uuid)
    {
        $ret = $this->delBase("general.HTTPAccesses.HTTPAccess", $uuid);
        $mdl = $this->getModel();
        $this->repackPriority($mdl);
        $mdl->serializeToConfig();
        Config::getInstance()->save();
        return $ret;
    }

    /**
     * delete http_access by uuid
     * @param string $uuid item unique id
     * @return array result status
     * @throws \OPNsense\Base\ModelException
     * @throws \Phalcon\Validation\Exception
     */
    public function updownACLAction($uuid)
    {

        if (!$this->request->isPost() || $uuid == null || !$this->request->hasPost("command")) {
            return ["result" => "failed"];
        }

        $mdlProxyUserACL = new ProxyUserACL();
        $count = $this->repackPriority($mdlProxyUserACL);
        $nodes = $mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->getNodes();
        $acl = $nodes[$uuid];
        $priority = $acl["Priority"];
        switch ($this->request->getPost("command")) {
            case "up":
                $new_priority = $priority - 1;
                if ($new_priority < 0) {
                    return ["result" => "success"];
                }
                break;

            case "down":
                $new_priority = $priority + 1;
                if ($new_priority >= $count) {
                    return ["result" => "success"];
                }
                break;

            default:
                return ["result" => "failed"];
        }
        foreach ($nodes as $key => $node) {
            if ($node["Priority"] == $new_priority) {
                $mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->{$key}->Priority = (string)$priority;
                $mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->{$uuid}->Priority = (string)$new_priority;
                $mdlProxyUserACL->serializeToConfig();
                Config::getInstance()->save();
                return ['result' => 'success'];
            }
        }
    }

    /**
     * repack rules priority
     * @param $mdlProxyUserACL
     * @return int
     */
    private function repackPriority($mdlProxyUserACL)
    {
        $count = 0;
        foreach ($mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->sortedBy("Priority") as $node) {
            $key = $node->getAttributes()["uuid"];
            $mdlProxyUserACL->general->HTTPAccesses->HTTPAccess->{$key}->Priority = (string)$count++;
        }
        return $count;
    }
}
