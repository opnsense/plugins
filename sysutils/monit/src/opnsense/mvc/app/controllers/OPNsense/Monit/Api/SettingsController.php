<?php

/**
 *    Copyright (C) 2017 EURO-LOG AG
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

namespace OPNsense\Monit\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\Monit\Monit;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\Monit
 */
class SettingsController extends ApiControllerBase
{


    //// GENRAL SETTINGS ////
    /**
    * retrieve monit general settings or return defaults
    * @return array
    */
    public function getGeneralAction()
    {
        return $this->get('general');
    }

    /**
     * update monit general settings with given properties
     * @return array
     */
    public function setGeneralAction()
    {
        return $this->set('general');
    }

    //// ALERT SETTINGS ////

    /**
     * search alert
     * @return array
     */
    public function searchAlertAction()
    {
        $fields = array("enabled", "recipient", "noton", "events", "description");
        return $this->search('alert', $fields);
    }

    /**
     * retrieve monit alert settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getAlertAction($uuid = null)
    {
        return $this->get('alert', $uuid);
    }

    /**
     * set monit alert parameter
     * @param $uuid item unique id
     * @return array
     */
    public function setAlertAction($uuid = null)
    {
        if ($uuid != null) {
            return $this->set('alert', $uuid);
        }
        return array("result" => "failed");
    }

    /**
     * add monit alert parameter
     * @return array
     */
    public function addAlertAction()
    {
        return $this->set('alert', null, true);
    }

    /**
     * delete monit alert parameter
     * @param $uuid item unique id
     * @return array
     */
    public function delAlertAction($uuid = null)
    {
        if ($uuid != null) {
            return $this->del('alert', $uuid);
        }
        return array("result" => "failed");
    }

    /**
     * toggle monit alert by uuid (enable/disable)
     * @param $uuid item unique id
     * @return array
     */
    public function toggleAlertAction($uuid)
    {
        if ($uuid != null) {
            return $this->toggle('alert', $uuid);
        }
        return array("result" => "failed");
    }

    //// SERVICE SETTINGS ////

    /**
     * search service
     * @return array
     */
    public function searchServiceAction()
    {
        $fields = array("enabled", "name", "type", "description");
        return $this->search('service', $fields);
    }

    /**
     * retrieve monit service settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getServiceAction($uuid = null)
    {
        return $this->get('service', $uuid);
    }

    /**
     * set monit service parameter
     * @param $uuid item unique id
     * @return array
     */
    public function setServiceAction($uuid = null)
    {
        if ($uuid != null) {
            return $this->set('service', $uuid);
        }
        return array("result" => "failed");
    }

    /**
     * add monit service parameter
     * @return array
     */
    public function addServiceAction()
    {
        return $this->set('service', null, true);
    }

    /**
     * delete monit service parameter
     * @param $uuid item unique id
     * @return array
     */
    public function delServiceAction($uuid = null)
    {
        if ($uuid != null) {
            return $this->del('service', $uuid);
        }
        return array("result" => "failed");
    }

    /**
     * toggle monit service by uuid (enable/disable)
     * @param $uuid item unique id
     * @return array
     */
    public function toggleServiceAction($uuid)
    {
        if ($uuid != null) {
            return $this->toggle('service', $uuid);
        }
        return array("result" => "failed");
    }

    //// SERVICE TEST SETTINGS ////

    /**
     * search test
     * @return array
     */
    public function searchTestAction()
    {
        $fields = array("name", "condition", "action");
        return $this->search('test', $fields);
    }

    /**
     * retrieve monit service test settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getTestAction($uuid = null)
    {
        return $this->get('test', $uuid);
    }

    /**
     * set monit service test parameter
     * @param $uuid item unique id
     * @return array
     */
    public function setTestAction($uuid = null)
    {
        if ($uuid != null) {
            return $this->set('test', $uuid);
        }
        return array("result" => "failed");
    }

    /**
     * add monit service test parameter
     * @return array
     */
    public function addTestAction()
    {
        return $this->set('test', null, true);
    }

    /**
     * delete monit service test parameter
     * @param $uuid item unique id
     * @return array
     */
    public function delTestAction($uuid = null)
    {
        if ($uuid != null) {
            return $this->del('test', $uuid);
        }
        return array("result" => "failed");
    }

    //// ABSTRACT FUNCTIONS ////

    /**
     * retrieve monit settings
     * @param $nodeType
     * @param $uuid
     * @return result array
     */
    private function get($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isGet() && $nodeType != null) {
            $mdlMonit = new Monit();
            if ($uuid != null) {
                $node = $mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
            } else {
                if ($nodeType == 'general') {
                    $node = $mdlMonit->getNodeByReference($nodeType);
                } else {
                    $node = $mdlMonit->$nodeType->Add();
                }
            }
            if ($node != null) {
                $result[$nodeType] = $node->getNodes();
                $result["result"] = "ok";
            }
        }
        return $result;
    }

    /**
     * set monit properties
     * @param $nodeType
     * @param $uuid
     * @parm $action set or add node
     * @return result array
     */
    private function set($nodeType = null, $uuid = null, $add = false)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("monit") && $nodeType != null) {
            $mdlMonit = new Monit();
            if ($add == false) { // set node
                if ($uuid != null) {
                    $node = $mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
                } else {
                    if ($nodeType == 'general') {
                        $node = $mdlMonit->getNodeByReference($nodeType);
                    } else {
                        $node = $mdlMonit->$nodeType->Add();
                    }
                }
            } else {
                $node = $mdlMonit->$nodeType->Add();
            }
            if ($node != null) {
                $monitInfo = $this->request->getPost("monit");

                // perform plugin specific validations
                if ($nodeType == 'service') {
                    switch ($monitInfo[$nodeType]['type']) {
                        case 'process':
                            if (empty($monitInfo[$nodeType]['pidfile']) && empty($monitInfo[$nodeType]['match'])) {
                                $result["validations"]['monit.service.pidfile'] = "Please set at least one of Pidfile or Match.";
                                $result["validations"]['monit.service.match'] = $result["validations"]['monit.service.pidfile'];
                            }
                            break;
                        case 'host':
                            if (empty($monitInfo[$nodeType]['address'])) {
                                $result["validations"]['monit.service.address'] = "Address is mandatory for 'Remote Host' checks.";
                            }
                            break;
                        case 'network':
                            if (empty($monitInfo[$nodeType]['address']) && empty($monitInfo[$nodeType]['interface'])) {
                                $result["validations"]['monit.service.address'] = "Please set at least one of Address or Interface.";
                                $result["validations"]['monit.service.interface'] = $result["validations"]['monit.service.address'];
                            }
                            break;
                        case 'system':
                            break;
                        default:
                            if (empty($monitInfo[$nodeType]['path'])) {
                                $result["validations"]['monit.service.path'] = "Path is mandatory.";
                            }
                    }
                }

                $node->setNodes($monitInfo[$nodeType]);
                $valMsgs = $mdlMonit->performValidation();
                foreach ($valMsgs as $field => $msg) {
                    $fieldnm = str_replace($node->__reference, "monit." . $nodeType, $msg->getField());
                    $result["validations"][$fieldnm] = $msg->getMessage();
                }
                if ($valMsgs->count() == 0) {
                    $mdlMonit->serializeToConfig();
                    Config::getInstance()->save();
                    $svcMonit = new ServiceController();
                    $result = $svcMonit->configtestAction();
                    if ($nodeType == 'general' && $node->enabled->__toString() == 0) {
			$result['stop'] = $svcMonit->stopAction();
                    }
                }
            }
        }
        return $result;
    }

    /**
     * delete monit properties
     * @param $nodeType
     * @param $uuid
     * @return result array
     */
    private function del($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $nodeType != null) {
            $mdlMonit = new Monit();
            if ($uuid != null) {
                $node = $mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
                if ($node != null) {
                    if ($mdlMonit->$nodeType->del($uuid) == true) {
                        // remove test from services
                        if ($nodeType == 'test') {
                            // get a list of all services
                            $services = $mdlMonit->service->getNodes();
                            foreach ($services as $serviceUuid => $service) {
                                foreach ($service['tests'] as $testUuid => $test) {
                                    // service has a reference to a test
                                    if ($testUuid == $uuid) {
                                        // get service model and remove $uuid from tests
                                        $ref = 'service.' . $serviceUuid . '.tests';
                                        $tstNode = $mdlMonit->getNodeByReference($ref);
                                        $svcTests = str_replace($uuid, '', $tstNode->__toString());
                                        $svcTests = str_replace(',,', ',', $svcTests);
                                        $svcTests = rtrim($svcTests, ',');
                                        $svcTests = ltrim($svcTests, ',');
                                        $mdlMonit->setNodeByReference($ref, $svcTests);
                                    }
                                }
                            }
                        }
                        $mdlMonit->serializeToConfig();
                        Config::getInstance()->save();
                        $svcMonit = new ServiceController();
                        $result = $svcMonit->reloadAction();
                    }
                } else {
                    $result['result'] = "not found";
                }
            } else {
                $result['result'] = "uuid not given";
            }
        }
        return $result;
    }

    /**
     * toggle monit items (enable/disable)
     * @param $nodeType
     * @param $uuid
     * @return result array
     */
    private function toggle($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $nodeType != null) {
            $mdlMonit = new Monit();
            if ($uuid != null) {
                $node = $mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $mdlMonit->serializeToConfig();
                    Config::getInstance()->save();
                    $svcMonit = new ServiceController();
                    $result= $svcMonit->reloadAction();
                } else {
                    $result['result'] = "not found";
                }
            } else {
                $result['result'] = "uuid not given";
            }
        }
        return $result;
    }

    /**
     * search monit settings
     * @param $nodeType
     * @param requested field list
     * @return array
     */
    private function search($nodeType = null, &$fields = null)
    {
        $this->sessionClose();
        if ($nodeType != null) {
            $mdlMonit = new Monit();
            $grid = new UIModelGrid($mdlMonit->$nodeType);
            return $grid->fetchBindRequest($this->request, $fields);
        }
    }
}
