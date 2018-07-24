<?php

/**
 *    Copyright (C) 2017-2018 Smart-Soft
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
use \OPNsense\Auth\AuthenticationFactory;

/**
 * Class SettingsController Handles settings related API actions for the ProxyUserACL
 * @package OPNsense\ProxyUserACL
 */
class UsersController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'User';
    static protected $internalModelClass = '\OPNsense\ProxyUserACL\ProxyUserACL';

    /**
     * search users and groups
     * @return array
     * @throws \ReflectionException
     */
    public function searchUserAction()
    {
        return $this->searchBase(
            "general.Users.User",
            array('Names', "Server", "Group", 'uuid'),
            "Names"
        );
    }

    /**
     * retrieve users and groups settings or return defaults
     * @param null $uuid
     * @return array
     * @throws \ReflectionException
     */
    public function getUserAction($uuid = null)
    {
        return $this->getBase("User", "general.Users.User", $uuid);
    }

    /**
     * add new user or group and set with attributes from post
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function addUserAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("User")) {
            $result = array("result" => "failed", "validations" => array());
            $mdl = $this->getModel();
            $node = $mdl->general->Users->User->Add();
            $post = $this->request->getPost("User");
            $post["Hex"] = self::strToHex(implode(":", array_filter(explode(",", $post["Names"]))));
            $node->setNodes($post);
            $find = $this->checkName($post);
            if ($find !== true) {
                $result["validations"]["User.Names"] = $find;
            }
            $valMsgs = $mdl->performValidation();

            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "User", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }

            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdl->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    /**
     * update users or groups item
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function setUserAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("User")) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                $node = $mdl->getNodeByReference("general.Users.User." . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());

                    $post = $this->request->getPost("User");
                    $post["Hex"] = self::strToHex(implode(":", array_filter(explode(",", $post["Names"]))));
                    $node->setNodes($post);
                    $find = $this->checkName($post);
                    if ($find !== true) {
                        $result["validations"]["User.Names"] = $find;
                    }
                    $valMsgs = $mdl->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "User", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }

                    if (count($result['validations']) == 0) {
                        // save config if validated correctly
                        $mdl->serializeToConfig();
                        Config::getInstance()->save();
                        $result = array("result" => "saved");
                    }
                    return $result;
                }
            }
        }
        return array("result" => "failed");
    }

    /**
     * delete user or group by uuid
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function delUserAction($uuid)
    {
        foreach ($this->getModel()->general->ACLs->ACL->getChildren() as $acl) {
            if (($users = $acl->Users) != null && isset($users->getNodeData()[$uuid]["selected"]) && $users->getNodeData()[$uuid]["selected"] == 1) {
                return ["result" => "value is used"];
            }
        }
        foreach ($this->getModel()->general->HTTPAccesses->HTTPAccess->getChildren() as $acl) {
            if (($users = $acl->Users) != null && isset($users->getNodeData()[$uuid]["selected"]) && $users->getNodeData()[$uuid]["selected"] == 1) {
                return ["result" => "value is used"];
            }
        }
        foreach ($this->getModel()->general->ICAPs->ICAP->getChildren() as $acl) {
            if (($users = $acl->Users) != null && isset($users->getNodeData()[$uuid]["selected"]) && $users->getNodeData()[$uuid]["selected"] == 1) {
                return ["result" => "value is used"];
            }
        }
        return $this->delBase("general.Users.User", $uuid);
    }

    /**
     * check existing user and group in local base or LDAP
     * @param $post
     * @return bool|string
     */
    private function checkName($post)
    {
        $names = $post["Names"];
        $type = $post["Group"];

        $authFactory = new AuthenticationFactory();
        $server = $authFactory->listServers()[$post["Server"]];

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
                if ($ldap_auth_server->connect($ldapBindURL, $server["ldap_binddn"], $server["ldap_bindpw"]) == false) {
                    return gettext("Error connecting to LDAP server");
                }

                foreach (explode(",", $names) as $name) {
                    try {
                        $users = $ldap_auth_server->searchUsers($name, $server["ldap_attr_user"]);
                    } catch (\Exception $e) {
                        break;
                    }
                    if ($users === false || count($users) == 0) {
                        return sprintf(gettext('The %s %s does not exist'), $type, $name);
                    }
                }
                break;

            case "local":
                foreach (explode(",", $names) as $name) {
                    $find = false;
                    foreach (Config::getInstance()->object()->system->{"$type"} as $item) {
                        if ($name == (string)$item->name) {
                            $find = true;
                            break;
                        }
                    }
                    if (!$find) {
                        return sprintf(gettext('The %s %s does not exist'), $type, $name);
                    }
                }
                break;

            default:
                break;
        }
        return true;
    }

    /**
     * convert user name to HEX
     * @param $string
     * @return string
     */
    public static function strToHex($string)
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }
}
