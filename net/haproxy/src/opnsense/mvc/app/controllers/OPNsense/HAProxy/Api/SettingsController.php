<?php
/**
 *    Copyright (C) 2016 Frank Wall
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
namespace OPNsense\HAProxy\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\HAProxy\HAProxy;
use \OPNsense\Core\Config;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\HAProxy
 */
class SettingsController extends ApiControllerBase
{
    /**
     * Validate and save model after update or insertion.
     * Use the reference node and tag to rename validation output for a specific
     * node to a new offset, which makes it easier to reference specific uuids
     * without having to use them in the frontend descriptions.
     * @param $mdl model reference
     * @param $node reference node, to use as relative offset
     * @param $reference reference for validation output, used to rename the validation output keys
     * @return array result / validation output
     */
    private function save($mdl, $node = null, $reference = null)
    {
        $result = array("result"=>"failed","validations" => array());
        // perform validation
        $valMsgs = $mdl->performValidation();
        foreach ($valMsgs as $field => $msg) {
            // replace absolute path to attribute for relative one at uuid.
            if ($node != null) {
                $fieldnm = str_replace($node->__reference, $reference, $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            } else {
                $result["validations"][$msg->getField()] = $msg->getMessage();
            }
        }

        // serialize model to config and save when there are no validation errors
        if (count($result['validations']) == 0) {
            // save config if validated correctly
            $mdl->serializeToConfig();

            Config::getInstance()->save();
            $result = array("result" => "saved");
        }

        return $result;
    }

    /**
     * retrieve haproxy settings
     * @return array
     */
    public function getAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdlProxy = new HAProxy();
            $result['haproxy'] = $mdlProxy->getNodes();
        }

        return $result;
    }

    /**
     * update haproxy configuration fields
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->hasPost("haproxy")) {
            // load model and update with provided data
            $mdlProxy = new HAProxy();
            $mdlProxy->setNodes($this->request->getPost("haproxy"));

            // perform validation
            $valMsgs = $mdlProxy->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["haproxy.".$msg->getField()] = $msg->getMessage();
            }

            // serialize model to config and save
            if ($valMsgs->count() == 0) {
                $mdlProxy->serializeToConfig();
                $cnf = Config::getInstance();
                $cnf->save();
                $result["result"] = "saved";
            }
        }

        return $result;
    }

    /**
     * retrieve frontend settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getFrontendAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('frontends.frontend.'.$uuid);
            if ($node != null) {
                // return node
                return array("frontend" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->frontends->frontend->add();
            return array("frontend" => $node->getNodes());
        }
        return array();
    }

    /**
     * update frontend with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setFrontendAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("frontend")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('frontends.frontend.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("frontend"));
                    return $this->save($mdlCP, $node, "frontend");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new frontend and set with attributes from post
     * @return array
     */
    public function addFrontendAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("frontend")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->frontends->frontend->Add();
            $node->setNodes($this->request->getPost("frontend"));
            return $this->save($mdlCP, $node, "frontend");
        }
        return $result;
    }

    /**
     * delete frontend by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delFrontendAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->frontends->frontend->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * toggle frontend by uuid (enable/disable)
     * @param $uuid item unique id
     * @param $enabled desired state enabled(1)/disabled(0), leave empty for toggle
     * @return array status
     */
    public function toggleFrontendAction($uuid, $enabled = null)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('frontends.frontend.' . $uuid);
                if ($node != null) {
                    if ($enabled == "0" || $enabled == "1") {
                        $node->enabled = (string)$enabled;
                    } elseif ((string)$node->enabled == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $result['result'] = $node->enabled;
                    // if item has toggled, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     * search haproxy frontends
     * @return array
     */
    public function searchFrontendsAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->frontends->frontend);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "description"),
            "name"
        );
    }

    /**
     * retrieve backend settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getBackendAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('backends.backend.'.$uuid);
            if ($node != null) {
                // return node
                return array("backend" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->backends->backend->add();
            return array("backend" => $node->getNodes());
        }
        return array();
    }

    /**
     * update backend with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setBackendAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("backend")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('backends.backend.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("backend"));
                    return $this->save($mdlCP, $node, "backend");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new backend and set with attributes from post
     * @return array
     */
    public function addBackendAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("backend")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->backends->backend->Add();
            $node->setNodes($this->request->getPost("backend"));
            return $this->save($mdlCP, $node, "backend");
        }
        return $result;
    }

    /**
     * delete backend by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delBackendAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->backends->backend->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * toggle backend by uuid (enable/disable)
     * @param $uuid item unique id
     * @param $enabled desired state enabled(1)/disabled(0), leave empty for toggle
     * @return array status
     */
    public function toggleBackendAction($uuid, $enabled = null)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('backends.backend.' . $uuid);
                if ($node != null) {
                    if ($enabled == "0" || $enabled == "1") {
                        $node->enabled = (string)$enabled;
                    } elseif ((string)$node->enabled == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $result['result'] = $node->enabled;
                    // if item has toggled, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     * search haproxy backends
     * @return array
     */
    public function searchBackendsAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->backends->backend);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "description"),
            "name"
        );
    }

    /**
     * retrieve server settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getServerAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('servers.server.'.$uuid);
            if ($node != null) {
                // return node
                return array("server" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->servers->server->add();
            return array("server" => $node->getNodes());
        }
        return array();
    }

    /**
     * update server with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setServerAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("server")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('servers.server.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("server"));
                    return $this->save($mdlCP, $node, "server");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new server and set with attributes from post
     * @return array
     */
    public function addServerAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("server")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->servers->server->Add();
            $node->setNodes($this->request->getPost("server"));
            return $this->save($mdlCP, $node, "server");
        }
        return $result;
    }

    /**
     * delete server by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delServerAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->servers->server->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * search servers
     * @return array
     */
    public function searchServersAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->servers->server);
        return $grid->fetchBindRequest(
            $this->request,
            array("name", "address", "port", "description"),
            "name"
        );
    }

    /**
     * retrieve healthcheck settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getHealthcheckAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('healthchecks.healthcheck.'.$uuid);
            if ($node != null) {
                // return node
                return array("healthcheck" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->healthchecks->healthcheck->add();
            return array("healthcheck" => $node->getNodes());
        }
        return array();
    }

    /**
     * update healthcheck with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setHealthcheckAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("healthcheck")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('healthchecks.healthcheck.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("healthcheck"));
                    return $this->save($mdlCP, $node, "healthcheck");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new healthcheck and set with attributes from post
     * @return array
     */
    public function addHealthcheckAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("healthcheck")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->healthchecks->healthcheck->Add();
            $node->setNodes($this->request->getPost("healthcheck"));
            return $this->save($mdlCP, $node, "healthcheck");
        }
        return $result;
    }

    /**
     * delete healthcheck by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delHealthcheckAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->healthchecks->healthcheck->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * search healthchecks
     * @return array
     */
    public function searchHealthchecksAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->healthchecks->healthcheck);
        return $grid->fetchBindRequest(
            $this->request,
            array("name", "description"),
            "name"
        );
    }

    /**
     * retrieve acl settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getAclAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('acls.acl.'.$uuid);
            if ($node != null) {
                // return node
                return array("acl" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->acls->acl->add();
            return array("acl" => $node->getNodes());
        }
        return array();
    }

    /**
     * update acl with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setAclAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("acl")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('acls.acl.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("acl"));
                    return $this->save($mdlCP, $node, "acl");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new acl and set with attributes from post
     * @return array
     */
    public function addAclAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("acl")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->acls->acl->Add();
            $node->setNodes($this->request->getPost("acl"));
            return $this->save($mdlCP, $node, "acl");
        }
        return $result;
    }

    /**
     * delete acl by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delAclAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->acls->acl->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * search acls
     * @return array
     */
    public function searchAclsAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->acls->acl);
        return $grid->fetchBindRequest(
            $this->request,
            array("name", "description"),
            "name"
        );
    }

    /**
     * retrieve action settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getActionAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('actions.action.'.$uuid);
            if ($node != null) {
                // return node
                return array("action" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->actions->action->add();
            return array("action" => $node->getNodes());
        }
        return array();
    }

    /**
     * update action with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setActionAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("action")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('actions.action.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("action"));
                    return $this->save($mdlCP, $node, "action");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new action and set with attributes from post
     * @return array
     */
    public function addActionAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("action")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->actions->action->Add();
            $node->setNodes($this->request->getPost("action"));
            return $this->save($mdlCP, $node, "action");
        }
        return $result;
    }

    /**
     * delete action by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delActionAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->actions->action->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * search actions
     * @return array
     */
    public function searchActionsAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->actions->action);
        return $grid->fetchBindRequest(
            $this->request,
            array("name", "description"),
            "name"
        );
    }

    /**
     * retrieve lua settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getLuaAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('luas.lua.'.$uuid);
            if ($node != null) {
                // return node
                return array("lua" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->luas->lua->add();
            return array("lua" => $node->getNodes());
        }
        return array();
    }

    /**
     * update lua with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setLuaAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("lua")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('luas.lua.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("lua"));
                    return $this->save($mdlCP, $node, "lua");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new lua and set with attributes from post
     * @return array
     */
    public function addLuaAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("lua")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->luas->lua->Add();
            $node->setNodes($this->request->getPost("lua"));
            return $this->save($mdlCP, $node, "lua");
        }
        return $result;
    }

    /**
     * delete lua by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delLuaAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->luas->lua->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * toggle lua by uuid (enable/disable)
     * @param $uuid item unique id
     * @param $enabled desired state enabled(1)/disabled(0), leave empty for toggle
     * @return array status
     */
    public function toggleLuaAction($uuid, $enabled = null)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('luas.lua.' . $uuid);
                if ($node != null) {
                    if ($enabled == "0" || $enabled == "1") {
                        $node->enabled = (string)$enabled;
                    } elseif ((string)$node->enabled == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $result['result'] = $node->enabled;
                    // if item has toggled, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     * search luas
     * @return array
     */
    public function searchLuasAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->luas->lua);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "description"),
            "name"
        );
    }

    /**
     * retrieve errorfile settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getErrorfileAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('errorfiles.errorfile.'.$uuid);
            if ($node != null) {
                // return node
                return array("errorfile" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->errorfiles->errorfile->add();
            return array("errorfile" => $node->getNodes());
        }
        return array();
    }

    /**
     * update errorfile with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setErrorfileAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("errorfile")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('errorfiles.errorfile.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("errorfile"));
                    return $this->save($mdlCP, $node, "errorfile");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new errorfile and set with attributes from post
     * @return array
     */
    public function addErrorfileAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("errorfile")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->errorfiles->errorfile->Add();
            $node->setNodes($this->request->getPost("errorfile"));
            return $this->save($mdlCP, $node, "errorfile");
        }
        return $result;
    }

    /**
     * delete errorfile by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delErrorfileAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->errorfiles->errorfile->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * search errorfiles
     * @return array
     */
    public function searchErrorfilesAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->errorfiles->errorfile);
        return $grid->fetchBindRequest(
            $this->request,
            array("name", "description"),
            "name"
        );
    }

    /**
     * retrieve mapfile settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getMapfileAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('mapfiles.mapfile.'.$uuid);
            if ($node != null) {
                // return node
                return array("mapfile" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->mapfiles->mapfile->add();
            return array("mapfile" => $node->getNodes());
        }
        return array();
    }

    /**
     * update mapfile with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setMapfileAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("mapfile")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('mapfiles.mapfile.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("mapfile"));
                    return $this->save($mdlCP, $node, "mapfile");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new mapfile and set with attributes from post
     * @return array
     */
    public function addMapfileAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("mapfile")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->mapfiles->mapfile->Add();
            $node->setNodes($this->request->getPost("mapfile"));
            return $this->save($mdlCP, $node, "mapfile");
        }
        return $result;
    }

    /**
     * delete mapfile by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delMapfileAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->mapfiles->mapfile->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * search mapfiles
     * @return array
     */
    public function searchMapfilesAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->mapfiles->mapfile);
        return $grid->fetchBindRequest(
            $this->request,
            array("name", "description"),
            "name"
        );
    }

    /**
     * retrieve cpu settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getCpuAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('cpus.cpu.'.$uuid);
            if ($node != null) {
                // return node
                return array("cpu" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->cpus->cpu->add();
            return array("cpu" => $node->getNodes());
        }
        return array();
    }

    /**
     * update cpu with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setCpuAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("cpu")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('cpus.cpu.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("cpu"));
                    return $this->save($mdlCP, $node, "cpu");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new cpu and set with attributes from post
     * @return array
     */
    public function addCpuAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("cpu")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->cpus->cpu->Add();
            $node->setNodes($this->request->getPost("cpu"));
            return $this->save($mdlCP, $node, "cpu");
        }
        return $result;
    }

    /**
     * delete cpu by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delCpuAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->cpus->cpu->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * toggle cpu by uuid (enable/disable)
     * @param $uuid item unique id
     * @param $enabled desired state enabled(1)/disabled(0), leave empty for toggle
     * @return array status
     */
    public function toggleCpuAction($uuid, $enabled = null)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('cpus.cpu.' . $uuid);
                if ($node != null) {
                    if ($enabled == "0" || $enabled == "1") {
                        $node->enabled = (string)$enabled;
                    } elseif ((string)$node->enabled == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $result['result'] = $node->enabled;
                    // if item has toggled, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     * search cpus
     * @return array
     */
    public function searchCpusAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->cpus->cpu);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "process_id", "thread_id", "cpu_id"),
            "name"
        );
    }

    /**
     * retrieve group settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getGroupAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('groups.group.'.$uuid);
            if ($node != null) {
                // return node
                return array("group" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->groups->group->add();
            return array("group" => $node->getNodes());
        }
        return array();
    }

    /**
     * update group with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setGroupAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("group")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('groups.group.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("group"));
                    return $this->save($mdlCP, $node, "group");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new group and set with attributes from post
     * @return array
     */
    public function addGroupAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("group")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->groups->group->Add();
            $node->setNodes($this->request->getPost("group"));
            return $this->save($mdlCP, $node, "group");
        }
        return $result;
    }

    /**
     * delete group by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delGroupAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->groups->group->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * toggle group by uuid (enable/disable)
     * @param $uuid item unique id
     * @param $enabled desired state enabled(1)/disabled(0), leave empty for toggle
     * @return array status
     */
    public function toggleGroupAction($uuid, $enabled = null)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('groups.group.' . $uuid);
                if ($node != null) {
                    if ($enabled == "0" || $enabled == "1") {
                        $node->enabled = (string)$enabled;
                    } elseif ((string)$node->enabled == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $result['result'] = $node->enabled;
                    // if item has toggled, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     * search groups
     * @return array
     */
    public function searchGroupsAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->groups->group);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "description"),
            "name"
        );
    }

    /**
     * retrieve user settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getUserAction($uuid = null)
    {
        $mdlCP = new HAProxy();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('users.user.'.$uuid);
            if ($node != null) {
                // return node
                return array("user" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->users->user->add();
            return array("user" => $node->getNodes());
        }
        return array();
    }

    /**
     * update user with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setUserAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("user")) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('users.user.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("user"));
                    return $this->save($mdlCP, $node, "user");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new user and set with attributes from post
     * @return array
     */
    public function addUserAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("user")) {
            $mdlCP = new HAProxy();
            $node = $mdlCP->users->user->Add();
            $node->setNodes($this->request->getPost("user"));
            return $this->save($mdlCP, $node, "user");
        }
        return $result;
    }

    /**
     * delete user by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delUserAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                if ($mdlCP->users->user->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * toggle user by uuid (enable/disable)
     * @param $uuid item unique id
     * @param $enabled desired state enabled(1)/disabled(0), leave empty for toggle
     * @return array status
     */
    public function toggleUserAction($uuid, $enabled = null)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlCP = new HAProxy();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('users.user.' . $uuid);
                if ($node != null) {
                    if ($enabled == "0" || $enabled == "1") {
                        $node->enabled = (string)$enabled;
                    } elseif ((string)$node->enabled == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $result['result'] = $node->enabled;
                    // if item has toggled, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     * search users
     * @return array
     */
    public function searchUsersAction()
    {
        $this->sessionClose();
        $mdlCP = new HAProxy();
        $grid = new UIModelGrid($mdlCP->users->user);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "name", "description"),
            "name"
        );
    }
}
