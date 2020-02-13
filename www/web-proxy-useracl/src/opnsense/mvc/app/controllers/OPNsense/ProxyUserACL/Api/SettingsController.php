<?php

/**
 *    Copyright (C) 2017 Smart-Soft
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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;
use OPNsense\Base\UIModelGrid;
use OPNsense\Auth\AuthenticationFactory;
use OPNsense\Proxy\Proxy;

/**
 * Class SettingsController Handles settings related API actions for the ProxyUserACL
 * @package OPNsense\ProxySSO
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'proxyuseracl';
    protected static $internalModelClass = '\OPNsense\ProxyUserACL\ProxyUserACL';

    /**
     *
     * search ACL
     * @return array
     */
    public function searchACLAction()
    {
        $this->sessionClose();
        $mdlProxyUserACL = $this->getModel();
        $grid = new UIModelGrid($mdlProxyUserACL->general->ACLs->ACL);
        return $grid->fetchBindRequest(
            $this->request,
            array('Group', 'Name', 'Domains', 'Black', 'Priority', 'uuid'),
            'Priority'
        );
    }

    /**
     *
     * add ACL
     * @return array
     */
    public function addACLAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("ACL")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlProxyUserACL = $this->getModel();
            $post = $this->request->getPost("ACL");
            $post["Hex"] = $this->strToHex($post["Name"]);

            $count = count($mdlProxyUserACL->general->ACLs->ACL->getNodes());
            if ($post["Priority"] > $count) {
                $post["Priority"] = $count;
            }
            foreach ($mdlProxyUserACL->general->ACLs->ACL->sortedBy("Priority", true) as $acl) {
                $key = $acl->getAttributes()["uuid"];
                $priority = (string)$mdlProxyUserACL->general->ACLs->ACL->{$key}->Priority;
                if ($priority < $post["Priority"]) {
                    break;
                }
                $mdlProxyUserACL->general->ACLs->ACL->{$key}->Priority = (string)($priority + 1);
            }
            $node = $mdlProxyUserACL->general->ACLs->ACL->Add();
            $node->setNodes($post);
            $find = $this->checkName($post["Name"], $post["Group"]);
            if ($find !== true) {
                $result["validations"]["ACL.Name"] = $find;
            }
            $valMsgs = $mdlProxyUserACL->performValidation();

            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "ACL", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }

            if (count($result['validations']) <= 0) {
                // save config if validated correctly
                $mdlProxyUserACL->serializeToConfig();
                Config::getInstance()->save();
                return array("result" => "saved");
            }
            return $result;
        }
        return $result;
    }

    /**
     *
     * get ACL
     * @return array
     */
    public function getACLAction($uuid = null)
    {
        $mdlProxyUserACL = $this->getModel();
        if ($uuid == null) {
            // generate new node, but don't save to disc
            $node = $mdlProxyUserACL->general->ACLs->ACL->add();
            return array("ACL" => $node->getNodes());
        }

        $node = $mdlProxyUserACL->getNodeByReference('general.ACLs.ACL.' . $uuid);
        if ($node != null) {
            return array("ACL" => $node->getNodes());
        }

        return array();
    }

    /**
     *
     * set ACL
     * @return array
     */
    public function setACLAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("ACL")) {
            $mdlProxyUserACL = $this->getModel();
            if ($uuid != null) {
                $node = $mdlProxyUserACL->getNodeByReference('general.ACLs.ACL.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $ACLInfo = $this->request->getPost("ACL");
                    $ACLInfo["Hex"] = $this->strToHex($ACLInfo["Name"]);
                    $old_priority = (string)$node->Priority;
                    $new_priority = $ACLInfo["Priority"];

                    if ($new_priority < $old_priority) {
                        if ($new_priority < 0) {
                            $new_priority = 0;
                        }

                        foreach ($mdlProxyUserACL->general->ACLs->ACL->sortedBy("Priority", true) as $acl) {
                            $key = $acl->getAttributes()["uuid"];
                            $priority = (string)$mdlProxyUserACL->general->ACLs->ACL->{$key}->Priority;
                            if ($priority < $new_priority) {
                                break;
                            }
                            if ($priority >= $old_priority) {
                                continue;
                            }
                            $mdlProxyUserACL->general->ACLs->ACL->{$key}->Priority = (string)($priority + 1);
                        }
                    } elseif (($new_priority > $old_priority)) {
                        $count = count($mdlProxyUserACL->general->ACLs->ACL->getNodes());
                        if ($new_priority >= $count) {
                            $new_priority = $count - 1;
                            $ACLInfo["Priority"] = $new_priority;
                        }
                        foreach ($mdlProxyUserACL->general->ACLs->ACL->sortedBy("Priority") as $acl) {
                            $key = $acl->getAttributes()["uuid"];
                            $priority = (string)$mdlProxyUserACL->general->ACLs->ACL->{$key}->Priority;
                            if ($priority > $new_priority) {
                                break;
                            }
                            if ($priority <= $old_priority) {
                                continue;
                            }
                            $mdlProxyUserACL->general->ACLs->ACL->{$key}->Priority = (string)($priority - 1);
                        }
                    }
                    $node->setNodes($ACLInfo);
                    $find = $this->checkName($ACLInfo["Name"], $ACLInfo["Group"]);
                    if ($find !== true) {
                        $result["validations"]["ACL.Name"] = $find;
                    }
                    $valMsgs = $mdlProxyUserACL->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "ACL", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }

                    if (count($result['validations']) > 0) {
                        return $result;
                    }

                    // save config if validated correctly
                    $mdlProxyUserACL->serializeToConfig();
                    Config::getInstance()->save();
                    return array("result" => "saved");
                }
            }
        }
        return $result;
    }

    /**
     *
     * del ACL
     * @return array
     */
    public function delACLAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $uuid != null) {
            $mdlProxyUserACL = $this->getModel();
            if ($mdlProxyUserACL->general->ACLs->ACL->del($uuid)) {
                // if item is removed, serialize to config and save
                $this->repackPriority();
                $mdlProxyUserACL->serializeToConfig();
                Config::getInstance()->save();
                $result['result'] = 'deleted';
            } else {
                $result['result'] = 'not found';
            }
        }

        return $result;
    }

    /**
     *
     * Change ACL priority
     * @param $uuid item unique id
     * @return array
     */
    public function updownACLAction($uuid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $uuid != null && $this->request->hasPost("command")) {
            $mdlProxyUserACL = $this->getModel();
            $count = $this->repackPriority();
            $nodes = $mdlProxyUserACL->general->ACLs->ACL->getNodes();
            $acl = $nodes[$uuid];
            $priority = $acl["Priority"];
            switch ($this->request->getPost("command")) {
                case "up":
                    $new_priority = $priority - 1;
                    if ($new_priority < 0) {
                        return array("result" => "success");
                    }
                    break;

                case "down":
                    $new_priority = $priority + 1;
                    if ($new_priority >= $count) {
                        return array("result" => "success");
                    }
                    break;

                default:
                    return array("result" => "failed");
            }
            foreach ($nodes as $key => $node) {
                if ($node["Priority"] == $new_priority) {
                    $mdlProxyUserACL->general->ACLs->ACL->{$key}->Priority = (string)$priority;
                    $mdlProxyUserACL->general->ACLs->ACL->{$uuid}->Priority = (string)$new_priority;
                    $mdlProxyUserACL->serializeToConfig();
                    Config::getInstance()->save();
                    return array('result' => 'success');
                }
            }
        }
        return $result;
    }

    private function checkName($user, $search)
    {
        $authFactory = new AuthenticationFactory();
        $servers = $authFactory->listServers();

        foreach (explode(',', (new Proxy())->forward->authentication->method) as $method) {
            if ($method == "") {
                return gettext("No authentication method selected");
            }
            $server = $servers[$method];
            switch ($server["type"]) {
                case "ldap":
                    if (!isset($server["ldap_binddn"])) {
                        return gettext("LDAP user name is not specified");
                    }

                    if (!isset($server["ldap_bindpw"])) {
                        return gettext("LDAP user password is not specified");
                    }

                    $ldapBindURL = strstr($server['ldap_urltype'], "Standard") ? "ldap://" : "ldaps://";
                    $ldapBindURL .= strpos($server['host'], "::") !== false ? "[{$server['host']}]" : $server['host'];
                    $ldapBindURL .= !empty($server['ldap_port']) ? ":{$server['ldap_port']}" : "";
                    $ldap_auth_server = $authFactory->get($server["name"]);
                    if (
                        $ldap_auth_server->connect(
                            $ldapBindURL,
                            $server["ldap_binddn"],
                            $server["ldap_bindpw"]
                        ) == false
                    ) {
                        return gettext("Error connecting to LDAP server");
                    }

                    try {
                        $users = $ldap_auth_server->searchUsers($user, $server["ldap_attr_user"]);
                    } catch (\Exception $e) {
                        break;
                    }
                    if ($users !== false && count($users) > 0) {
                        return true;
                    }
                    break;

                case "local":
                    foreach (Config::getInstance()->object()->system->{"$search"} as $item) {
                        if ($user == (string)$item->name) {
                            return true;
                        }
                    }
                    break;

                default:
                    break;
            }
        }
        return sprintf(gettext('The %s %s does not exist'), $search, $user);
    }

    private function repackPriority()
    {
        $mdlProxyUserACL = $this->getModel();
        $count = 0;
        foreach ($mdlProxyUserACL->general->ACLs->ACL->sortedBy("Priority") as $node) {
            $key = $node->getAttributes()["uuid"];
            $mdlProxyUserACL->general->ACLs->ACL->{$key}->Priority = (string)$count++;
        }
        return $count;
    }

    private function strToHex($string)
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }
}
