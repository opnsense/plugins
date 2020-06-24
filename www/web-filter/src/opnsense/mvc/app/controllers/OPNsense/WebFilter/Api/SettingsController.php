<?php

/**
 *    Copyright (C) 2018 Julio Camargo
 *    Copyright (C) 2018 Cloudfence
 *      based on Cron module by OPNsense/Deciso B.V.
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

namespace OPNsense\WebFilter\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\WebFilter\WebFilter;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController Handles settings related API actions for the WebFilter
 * @package OPNsense\WebFilter
 */
class SettingsController extends ApiControllerBase
{
    /**
     * retrieve rule settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getRuleAction($uuid = null)
    {
        $mdlWebFilter = new WebFilter();
        if ($uuid != null) {
            $node = $mdlWebFilter->getNodeByReference('rules.rule.' . $uuid);
            if ($node != null) {
                // return node
                return array("rule" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlWebFilter->rules->rule->add();
            $node->sequence = $mdlWebFilter->getMaxRuleSequence() + 1;
            return array("rule" => $node->getNodes());
        }
        return array();
    }

    /**
     * update rule with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setRuleAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("rule")) {
            $mdlWebFilter = new WebFilter();
            if ($uuid != null) {
                $node = $mdlWebFilter->getNodeByReference('rules.rule.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $ruleInfo = $this->request->getPost("rule");
                    if ($node->origin->__toString() != "webfilter") {
                        if ($ruleInfo["command"]!=$node->command->__toString()) {
                            $result["validations"]["rule.command"] = "This item has been created by " .
                                "another service, command and parameter may not be changed.";
                        }
                        if ($ruleInfo["parameters"]!=$node->parameters->__toString()) {
                            $result["validations"]["rule.parameters"] = "This item has been created by " .
                                "another service, command and parameter may not be changed. (was: " .
                                $node->parameters->__toString() . " )";
                        }
                    }

                    $node->setNodes($ruleInfo);
                    $valMsgs = $mdlWebFilter->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "rule", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }

                    if (count($result['validations']) == 0) {
                        // save config if validated correctly
                        $mdlWebFilter->serializeToConfig();
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
     * add new rule and set with attributes from post
     * @return array
     */
    public function addRuleAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("rule")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlWebFilter = new WebFilter();
            $node = $mdlWebFilter->rules->rule->Add();
            $node->setNodes($this->request->getPost("rule"));
            $node->origin = "webfilter"; // set origin to this component - webfilter are manually created rules.
            $valMsgs = $mdlWebFilter->performValidation();

            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "rule", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }

            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdlWebFilter->serializeToConfig();
                Config::getInstance()->save();
                $result = array("result" => "saved");
            }
            return $result;
        }
        return $result;
    }

    /**
     * delete rule by uuid ( only if origin is webfilter)
     * @param $uuid item unique id
     * @return array status
     */
    public function delRuleAction($uuid)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlWebFilter = new WebFilter();
            if ($uuid != null) {
                $node = $mdlWebFilter->getNodeByReference('rules.rule.' . $uuid);
                if ($node != null && (string)$node->origin == "webfilter" && $mdlWebFilter->rules->rule->del($uuid) == true) {
                    // if item is removed, serialize to config and save
                    $mdlWebFilter->serializeToConfig();
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
     * toggle rule by uuid (enable/disable)
     * @param $uuid item unique id
     * @return array status
     */
    public function toggleRuleAction($uuid)
    {

        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $mdlWebFilter = new WebFilter();
            if ($uuid != null) {
                $node = $mdlWebFilter->getNodeByReference('rules.rule.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $result['result'] = "Disabled";
                        $node->enabled = "0";
                    } else {
                        $result['result'] = "Enabled";
                        $node->enabled = "1";
                    }
                    // if item has toggled, serialize to config and save
                    $mdlWebFilter->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     *
     * search webfilter rules
     * @return array
     */
    public function searchRulesAction()
    {
        $this->sessionClose();
        $fields = array(
            "enabled",
            "sequence",
            "action",
            "name",
            "source",
            "destination",
            "description"
        );
        $mdlWebFilter = new WebFilter();
        $grid = new UIModelGrid($mdlWebFilter->rules->rule);
        return $grid->fetchBindRequest(
            $this->request,
            $fields,
            "description"
        );
    }
}
