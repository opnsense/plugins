<?php

/*
 *    Copyright (C) 2015-2017 Deciso B.V.
 *    Copyright (C) 2015 Jos Schellevis
 *    Copyright (C) 2017 Fabian Franz
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
 */

namespace OPNsense\Tor\Api;

use \OPNsense\Tor\HiddenServiceACL;
use \OPNsense\Core\Config;
use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Base\UIModelGrid;

class HiddenserviceaclController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'hiddenserviceacl';
    static protected $internalModelClass = '\OPNsense\Tor\HiddenServiceACL';
    public function searchaclAction()
    {
        $this->sessionClose();
        $mdl = $this->getModel();
        $grid = new UIModelGrid($mdl->hiddenserviceacl);
        return $grid->fetchBindRequest(
            $this->request,
            array('enabled', 'hiddenservice', 'port', 'target_host', 'target_port')
        );
    }
    public function getaclAction($uuid = null)
    {
        $mdl = $this->getModel();
        if ($uuid != null) {
            $node = $mdl->getNodeByReference('hiddenserviceacl.' . $uuid);
            if ($node != null) {
                // return node
                return array('hiddenserviceacl' => $node->getNodes());
            }
        } else {
            $node = $mdl->hiddenserviceacl->add();
            return array('hiddenserviceacl' => $node->getNodes());
        }
        return array();
    }
    public function addaclAction()
    {
        $result = array('result' => 'failed');
        if ($this->request->isPost() && $this->request->hasPost('hiddenserviceacl')) {
            $result = array('result' => 'failed', 'validations' => array());
            $mdl = $this->getModel();
            $node = $mdl->hiddenserviceacl->Add();
            $node->setNodes($this->request->getPost('hiddenserviceacl'));
            $valMsgs = $mdl->performValidation();

            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, 'hiddenserviceacl', $msg->getField());
                $result['validations'][$fieldnm] = $msg->getMessage();
            }

            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdl->serializeToConfig();
                Config::getInstance()->save();
                unset($result['validations']);
                $result['result'] = 'saved';
            }
        }
        return $result;
    }
    public function delaclAction($uuid)
    {

        $result = array('result' => 'failed');

        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                if ($mdl->hiddenserviceacl->del($uuid)) {
                    $mdl->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }
    public function setaclAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost('hiddenserviceacl')) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                $node = $mdl->getNodeByReference('hiddenserviceacl.' . $uuid);
                if ($node != null) {
                    $result = array('result' => 'failed', 'validations' => array());
                    $info = $this->request->getPost('hiddenserviceacl');

                    $node->setNodes($info);
                    $valMsgs = $mdl->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, 'hiddenserviceacl', $msg->getField());
                        $result['validations'][$fieldnm] = $msg->getMessage();
                    }

                    if (count($result['validations']) == 0) {
                        // save config if validated correctly
                        $mdl->serializeToConfig();
                        unset($result['validations']);
                        Config::getInstance()->save();
                        $result = array('result' => 'saved');
                    }
                    return $result;
                }
            }
        }
        return array('result' => 'failed');
    }
    public function toggle_handler($uuid, $element)
    {

        $result = array('result' => 'failed');

        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                $node = $mdl->getNodeByReference($element . '.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == '1') {
                        $result['result'] = 'Disabled';
                        $node->enabled = '0';
                    } else {
                        $result['result'] = 'Enabled';
                        $node->enabled = '1';
                    }
                    $mdl->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    public function toggleaclAction($uuid)
    {
        return $this->toggle_handler($uuid, 'hiddenserviceacl');
    }
}
