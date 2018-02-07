<?php

/**
 *    Copyright (C) 2018 EURO-LOG AG
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

namespace OPNsense\Relayd\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\Relayd\Relayd;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\Relayd
 */
class SettingsController extends ApiMutableModelControllerBase
{

    static protected $internalModelName = 'relayd';
    static protected $internalModelClass = '\OPNsense\Relayd\Relayd';

    /**
     * list with valid model node types
     */
    private $nodeTypes = array('general', 'host', 'tablecheck', 'table', 'protocol', 'virtualserver');

    /**
     * query relayd settings
     * @param $nodeType
     * @param $uuid
     * @return result array
     */
    public function getAction($nodeType = null, $uuid = null)
    {
        if ($nodeType != null) {
            $this->validateNodeType($nodeType);
            if ($nodeType == 'general') {
                $mdlRelayd = new Relayd();
                return array('result' => 'ok', 'general' => $mdlRelayd->getNodeByReference('general')->getNodes());
            } else {
                return $this->getBase($nodeType, $nodeType, $uuid);
            }
        }
        return array("result" => "failed");
    }

    /**
     * set relayd properties
     * @param $nodeType
     * @param $uuid
     * @param $action set or add node
     * @return status array
     */
    public function setAction($nodeType = null, $uuid = null)
    {
        if (!$this->request->isPost() || !$this->request->hasPost("relayd") || $nodeType == null) {
            return array('result' => 'failed');
        }

        $this->validateNodeType($nodeType);

        $relaydInfo = $this->request->getPost("relayd");

        if ($nodeType == 'general') {
            $mdlRelayd = new Relayd();
            $node = $mdlRelayd->getNodeByReference($nodeType);
            $node->setNodes($relaydInfo[$nodeType]);
            if ($relaydInfo['general']['enabled'] == '0') {
                $svcRelayd = new ServiceController();
                $svcRelayd->stopAction();
            }
            $mdlRelayd->serializeToConfig();
            Config::getInstance()->save();
            return array("result" => "saved");
        }

        // perform plugin specific validations
        if ($nodeType == 'virtualserver') {
            if ($relaydInfo[$nodeType]['type'] == 'redirect') {
                if ($relaydInfo[$nodeType]['transport_tablemode'] != 'least-states' &&
                    $relaydInfo[$nodeType]['transport_tablemode'] != 'roundrobin') {
                        $result["validations"]['relayd.virtualserver.transport_tablemode'] = "Scheduler '" . $relaydInfo[$nodeType]['transport_tablemode'] . "' not supported for redirects.";
                }
                if ($relaydInfo[$nodeType]['backuptransport_tablemode'] != 'least-states' &&
                        $relaydInfo[$nodeType]['backuptransport_tablemode'] != 'roundrobin') {
                        $result["validations"]['relayd.virtualserver.backuptransport_tablemode'] = "Scheduler '" . $relaydInfo[$nodeType]['backuptransport_tablemode'] . "' not supported for redirects.";
                }
            }
            if ($relaydInfo[$nodeType]['type'] == 'relay') {
                if ($relaydInfo[$nodeType]['transport_tablemode'] == 'least-states') {
                    $result["validations"]['relayd.virtualserver.transport_tablemode'] = "Scheduler '" . $relaydInfo[$nodeType]['transport_tablemode'] . "' not supported for relays.";
                }
                if ($relaydInfo[$nodeType]['backuptransport_tablemode'] == 'least-states') {
                    $result["validations"]['relayd.virtualserver.backuptransport_tablemode'] = "Scheduler '" . $relaydInfo[$nodeType]['backuptransport_tablemode'] . "' not supported for relays.";
                }
            }
        } elseif ($nodeType == 'tablecheck') {
            switch ($relaydInfo[$nodeType]['type']) {
                case 'send':
                    if (empty($relaydInfo[$nodeType]['expect'])) {
                        $result["validations"]['relayd.tablecheck.expect'] = "Expect Pattern cannot be empty.";
                    }
                    break;
                case 'script':
                    if (empty($relaydInfo[$nodeType]['path'])) {
                        $result["validations"]['relayd.tablecheck.path'] = "Script path cannot be empty.";
                    }
                    break;
                case 'http':
                    if (empty($relaydInfo[$nodeType]['path'])) {
                        $result["validations"]['relayd.tablecheck.path'] = "Path cannot be empty.";
                    }
                    if (empty($relaydInfo[$nodeType]['code']) && empty($relaydInfo[$nodeType]['digest'])) {
                        $result["validations"]['relayd.tablecheck.code'] = "Provide one of Response Code or Message Digest.";
                        $result["validations"]['relayd.tablecheck.digest'] = "Provide one of Response Code or Message Digest.";
                    }
                    break;
            }
        }
        if (!empty($result["validations"])) {
            return $result;
        }
        return $this->setBase('relayd', $nodeType, $uuid);
    }

    /**
     * delete relayd settings
     * @param $nodeType
     * @param $uuid
     * @return status array
     */
    public function delAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($nodeType != null) {
            $this->validateNodeType($nodeType);
            if ($uuid != null) {
                $mdlRelayd = new Relayd();
                $node = $mdlRelayd->getNodeByReference($nodeType . '.' . $uuid);
                if ($node != null) {
                    $nodeName = $mdlRelayd->getNodeByReference($nodeType . '.' . $uuid . '.name')->__toString();
                    if ($mdlRelayd->$nodeType->del($uuid) == true) {
                        // delete relations
                        switch ($nodeType) {
                            case 'host':
                                $this->deleteRelations('table', 'hosts', $uuid, 'host', $nodeName, $mdlRelayd);
                                break;
                            case 'tablecheck':
                                $this->deleteRelations('virtualserver', 'transport_tablecheck', $uuid, 'tablecheck', $nodeName, $mdlRelayd);
                                $this->deleteRelations('virtualserver', 'backuptransport_tablecheck', $uuid, 'tablecheck', $nodeName, $mdlRelayd);
                                break;
                            case 'table':
                                $this->deleteRelations('virtualserver', 'transport_table', $uuid, 'table', $nodeName, $mdlRelayd);
                                $this->deleteRelations('virtualserver', 'backuptransport_table', $uuid, 'table', $nodeName, $mdlRelayd);
                                break;
                            case 'protocol':
                                $this->deleteRelations('virtualserver', 'protocol', $uuid, 'protocol', $nodeName, $mdlRelayd);
                                break;
                        }
                        $mdlRelayd->serializeToConfig();
                        Config::getInstance()->save();
                        $result["result"] = "ok";
                    }
                }
            }
        }
        return $result;
    }

    /**
     * search relayd settings
     * @param $nodeType
     * @return result array
     */
    public function searchAction($nodeType = null)
    {
        if ($nodeType != null) {
            $this->validateNodeType($nodeType);
            $fields = array();
            switch ($nodeType) {
                case 'host':
                    $fields = array('name', 'address');
                    break;
                case 'tablecheck':
                    $fields = array('name', 'type');
                    break;
                case 'table':
                    $fields = array('enabled', 'name');
                    break;
                case 'protocol':
                    $fields = array('name', 'type');
                    break;
                case 'virtualserver':
                    $fields = array('enabled', 'name', 'type');
                    break;
            }
            return $this->searchBase($nodeType, $fields);
        }
    }

    /**
     * validate nodeType
     * @param $nodeType
     * @throws \Exception
     */
    private function validateNodeType($nodeType = null)
    {
        if (array_search($nodeType, $this->nodeTypes) === false) {
            throw new \Exception('unknown nodeType: ' . $nodeType);
        }
    }

    /**
     * delete relations
     * @param $nodeType
     * @param $uuid
     * @param $relNodeType
     * @param &$mdlRelayd
     * @throws \Exception
     */
    private function deleteRelations($nodeType = null, $nodeField = null, $relUuid = null, $relNodeType = null, $relNodeName = null, &$mdlRelayd = null)
    {
        $nodes = $mdlRelayd->$nodeType->getNodes();
        // get nodes with relations
        foreach ($nodes as $nodeUuid => $node) {
            // get relation uuids
            foreach ($node[$nodeField] as $fieldUuid => $field) {
                // remove uuid from field
                if ($fieldUuid == $relUuid) {
                    $refField = $nodeType . '.' . $nodeUuid . '.' . $nodeField;
                    $relNode = $mdlRelayd->getNodeByReference($refField);
                    $nodeRels = str_replace($relUuid, '', $relNode->__toString());
                    $nodeRels = str_replace(',,', ',', $nodeRels);
                    $nodeRels = rtrim($nodeRels, ',');
                    $nodeRels = ltrim($nodeRels, ',');
                    $mdlRelayd->setNodeByReference($refField, $nodeRels);
                    if ($relNode->isEmptyAndRequired()) {
                        $nodeName = $mdlRelayd->getNodeByReference($nodeType . '.' . $nodeUuid . '.name')->__toString();
                        throw new \Exception("Cannot delete $relNodeType '$relNodeName' from $nodeType '$nodeName'");
                    }
                }
            }
        }
    }
}
