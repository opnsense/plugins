<?php

/**
 *    Copyright (C) 2018 EURO-LOG AG
 *    Copyright (c) 2021 Deciso B.V.
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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;
use OPNsense\Relayd\Relayd;
use OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\Relayd
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'relayd';
    protected static $internalModelClass = '\OPNsense\Relayd\Relayd';

    /**
     * list with valid model node types
     */
    private $nodeTypes = array('general', 'host', 'tablecheck', 'table', 'protocol', 'virtualserver');


    /**
     * check if changes to the relayd settings were made
     * @return result array
     */
    public function dirtyAction()
    {
        $result = array('status' => 'ok');
        $result['relayd']['dirty'] = $this->getModel()->configChanged();
        return $result;
    }

    /**
     * query relayd settings
     * @param $nodeType
     * @param $uuid
     * @return result array
     */
    public function getAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isGet() && $nodeType != null) {
            $this->validateNodeType($nodeType);
            if ($nodeType == 'general') {
                $node = $this->getModel()->getNodeByReference($nodeType);
            } else {
                if ($uuid != null) {
                    $node = $this->getModel()->getNodeByReference($nodeType . '.' . $uuid);
                } else {
                    $node = $this->getModel()->$nodeType->Add();
                }
            }
            if ($node != null) {
                $result['relayd'] = array($nodeType => $node->getNodes());
                $result['status'] = 'ok';
            }
        }
        return $result;
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
        $result = array('result' => 'failed', 'validations' => array());
        if ($this->request->isPost() && $this->request->hasPost('relayd') && $nodeType != null) {
            $this->validateNodeType($nodeType);
            if ($nodeType == 'general') {
                $node = $this->getModel()->getNodeByReference($nodeType);
            } else {
                if ($uuid != null) {
                    $node = $this->getModel()->getNodeByReference($nodeType . '.' . $uuid);
                } else {
                    $node = $this->getModel()->$nodeType->Add();
                }
            }
            if ($node != null) {
                $relaydInfo = $this->request->getPost('relayd');

                // perform plugin specific validations
                if ($nodeType == 'virtualserver') {
                    // preset defaults for validations
                    if (empty($relaydInfo[$nodeType]['type'])) {
                        $relaydInfo[$nodeType]['type'] = $node->type->__toString();
                    }
                    if (empty($relaydInfo[$nodeType]['transport_tablemode'])) {
                        $relaydInfo[$nodeType]['transport_tablemode'] = $node->transport_tablemode->__toString();
                    }
                    if (empty($relaydInfo[$nodeType]['backuptransport_tablemode'])) {
                        $relaydInfo[$nodeType]['backuptransport_tablemode'] =
                        $node->backuptransport_tablemode->__toString();
                    }

                    if ($relaydInfo[$nodeType]['type'] == 'redirect') {
                        if (
                            $relaydInfo[$nodeType]['transport_tablemode'] != 'least-states' &&
                            $relaydInfo[$nodeType]['transport_tablemode'] != 'roundrobin'
                        ) {
                                $result['validations']['relayd.virtualserver.transport_tablemode'] = sprintf(
                                    gettext('Scheduler "%s" not supported for redirects.'),
                                    $relaydInfo[$nodeType]['transport_tablemode']
                                );
                        }
                        if (
                            $relaydInfo[$nodeType]['backuptransport_tablemode'] != 'least-states' &&
                            $relaydInfo[$nodeType]['backuptransport_tablemode'] != 'roundrobin'
                        ) {
                                $result['validations']['relayd.virtualserver.backuptransport_tablemode'] = sprintf(
                                    gettext('Scheduler "%s" not supported for redirects.'),
                                    $relaydInfo[$nodeType]['backuptransport_tablemode']
                                );
                        }
                        if (
                            $relaydInfo[$nodeType]['transport_type'] == 'route' &&
                            empty($relaydInfo[$nodeType]['routing_interface'])
                        ) {
                                $result['validations']['relayd.virtualserver.routing_interface'] =
                                    gettext('Routing interface cannot be empty');
                        }
                    }
                    if ($relaydInfo[$nodeType]['type'] == 'relay') {
                        if ($relaydInfo[$nodeType]['transport_tablemode'] == 'least-states') {
                            $result['validations']['relayd.virtualserver.transport_tablemode'] = sprintf(
                                gettext('Scheduler "%s" not supported for relays.'),
                                $relaydInfo[$nodeType]['transport_tablemode']
                            );
                        }
                        if ($relaydInfo[$nodeType]['backuptransport_tablemode'] == 'least-states') {
                            $result['validations']['relayd.virtualserver.backuptransport_tablemode'] = sprintf(
                                gettext('Scheduler "%s" not supported for relays.'),
                                $relaydInfo[$nodeType]['backuptransport_tablemode']
                            );
                        }
                    }
                } elseif ($nodeType == 'tablecheck') {
                    switch ($relaydInfo[$nodeType]['type']) {
                        case 'send':
                            if (empty($relaydInfo[$nodeType]['expect'])) {
                                $result['validations']['relayd.tablecheck.expect'] =
                                gettext('Expect Pattern cannot be empty.');
                            }
                            break;
                        case 'script':
                            if (empty($relaydInfo[$nodeType]['path'])) {
                                $result['validations']['relayd.tablecheck.path'] =
                                gettext('Script path cannot be empty.');
                            }
                            break;
                        case 'http':
                            if (empty($relaydInfo[$nodeType]['path'])) {
                                $result['validations']['relayd.tablecheck.path'] =
                                gettext('Path cannot be empty.');
                            }
                            if (empty($relaydInfo[$nodeType]['code']) && empty($relaydInfo[$nodeType]['digest'])) {
                                $result['validations']['relayd.tablecheck.code'] =
                                gettext('Provide one of Response Code or Message Digest.');
                                $result['validations']['relayd.tablecheck.digest'] =
                                gettext('Provide one of Response Code or Message Digest.');
                            }
                            break;
                    }
                }

                $node->setNodes($relaydInfo[$nodeType]);
                $valMsgs = $this->getModel()->performValidation();
                foreach ($valMsgs as $field => $msg) {
                    $fieldnm = str_replace($node->__reference, "relayd." . $nodeType, $msg->getField());
                    $result["validations"][$fieldnm] = $msg->getMessage();
                }
                if (empty($result["validations"])) {
                    unset($result["validations"]);
                    $this->getModel()->serializeToConfig();
                    $cfgRelayd = Config::getInstance()->save();
                    if ($this->getModel()->configDirty()) {
                        $result['status'] = 'ok';
                    }
                }
            }
        }
        return $result;
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
        Config::getInstance()->lock();
        if ($nodeType != null) {
            $this->validateNodeType($nodeType);
            if ($uuid != null) {
                $node = $this->getModel()->getNodeByReference($nodeType . '.' . $uuid);
                if ($node != null) {
                    $nodeName = $this->getModel()->getNodeByReference($nodeType . '.' . $uuid . '.name')->__toString();
                    if ($this->getModel()->$nodeType->del($uuid) == true) {
                        // delete relations
                        switch ($nodeType) {
                            case 'host':
                                $this->deleteRelations(
                                    'table',
                                    'hosts',
                                    $uuid,
                                    'host',
                                    $nodeName,
                                    $this->getModel()
                                );
                                break;
                            case 'tablecheck':
                                $this->deleteRelations(
                                    'virtualserver',
                                    'transport_tablecheck',
                                    $uuid,
                                    'tablecheck',
                                    $nodeName,
                                    $this->getModel()
                                );
                                $this->deleteRelations(
                                    'virtualserver',
                                    'backuptransport_tablecheck',
                                    $uuid,
                                    'tablecheck',
                                    $nodeName,
                                    $this->getModel()
                                );
                                break;
                            case 'table':
                                $this->deleteRelations(
                                    'virtualserver',
                                    'transport_table',
                                    $uuid,
                                    'table',
                                    $nodeName,
                                    $this->getModel()
                                );
                                $this->deleteRelations(
                                    'virtualserver',
                                    'backuptransport_table',
                                    $uuid,
                                    'table',
                                    $nodeName,
                                    $this->getModel()
                                );
                                break;
                            case 'protocol':
                                $this->deleteRelations(
                                    'virtualserver',
                                    'protocol',
                                    $uuid,
                                    'protocol',
                                    $nodeName,
                                    $this->getModel()
                                );
                                break;
                        }
                        $this->getModel()->serializeToConfig();
                        Config::getInstance()->save();
                        if ($this->getModel()->configDirty()) {
                            $result['status'] = 'ok';
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * toggle status
     * @param string $nodeType node type to address
     * @param string $uuid id to toggled
     * @param string|null $enabled set enabled by default
     * @return array status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function toggleAction($nodeType, $uuid, $enabled = null)
    {
        $this->getModel()->configDirty();
        return $this->toggleBase($nodeType, $uuid, $enabled);
    }

    /**
     * search relayd settings
     * @param $nodeType
     * @return result array
     */
    public function searchAction($nodeType = null)
    {
        $this->sessionClose();
        if ($this->request->isPost() && $nodeType != null) {
            $this->validateNodeType($nodeType);
            $grid = new UIModelGrid($this->getModel()->$nodeType);
            $fields = array();
            switch ($nodeType) {
                case 'host':
                    $fields = ['enabled', 'name', 'address'];
                    break;
                case 'tablecheck':
                    $fields = ['name', 'type'];
                    break;
                case 'table':
                    $fields = ['enabled', 'name'];
                    break;
                case 'protocol':
                    $fields = ['name', 'type'];
                    break;
                case 'virtualserver':
                    $fields = ['enabled', 'name', 'type', 'listen_address', 'listen_startport', 'listen_endport'];
                    break;
            }
            $result = $grid->fetchBindRequest($this->request, $fields);
            $result['dirty'] = $this->getModel()->configChanged();
            return $result;
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
    private function deleteRelations(
        $nodeType = null,
        $nodeField = null,
        $relUuid = null,
        $relNodeType = null,
        $relNodeName = null
    ) {
        $nodes = $this->getModel()->$nodeType->getNodes();
        // get nodes with relations
        foreach ($nodes as $nodeUuid => $node) {
            // get relation uuids
            foreach ($node[$nodeField] as $fieldUuid => $field) {
                // remove uuid from field
                if ($fieldUuid == $relUuid) {
                    $refField = $nodeType . '.' . $nodeUuid . '.' . $nodeField;
                    $relNode = $this->getModel()->getNodeByReference($refField);
                    $nodeRels = str_replace($relUuid, '', $relNode->__toString());
                    $nodeRels = str_replace(',,', ',', $nodeRels);
                    $nodeRels = rtrim($nodeRels, ',');
                    $nodeRels = ltrim($nodeRels, ',');
                    $this->getModel()->setNodeByReference($refField, $nodeRels);
                    if ($relNode->isEmptyAndRequired()) {
                        $nodeName = $this->getModel()->getNodeByReference("{$nodeType}.{$nodeUuid}.name")->__toString();
                        throw new \Exception("Cannot delete $relNodeType '$relNodeName' from $nodeType '$nodeName'");
                    }
                }
            }
        }
    }
}
