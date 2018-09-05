<?php

/*
    Copyright (c) 2018 Verb Networks Pty Ltd <contact@verbnetworks.com>
    Copyright (c) 2018 Nicholas de Jong <me@nicholasdejong.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
    ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace VerbNetworks\Autossh\Api;

use \OPNsense\Core\Backend;
use \OPNsense\Core\Config;
use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Base\UIModelGrid;
use \VerbNetworks\Autossh\Autossh;

class TunnelsController extends ApiControllerBase
{
    
    public function searchAction()
    {
        $this->sessionClose(); // close out long running actions
        $model = new Autossh();
        $grid = new UIModelGrid($model->tunnels->tunnel);
        
        $grid_data = $grid->fetchBindRequest(
            $this->request,
            array(
                'enabled', 'user', 'hostname', 'port', 'bind_interface', 'ssh_key',
                'local_forward', 'remote_forward', 'dynamic_forward'
            ),
            'hostname'
        );
        
        if (isset($grid_data['rows'])) {
            foreach ($grid_data['rows'] as $index => $tunnel) {
                $grid_data['rows'][$index]['connection'] = $tunnel['user'].'@'.$tunnel['hostname'];
                if (!empty($tunnel['port'])) {
                    $grid_data['rows'][$index]['connection'] =
                         $grid_data['rows'][$index]['connection'].':'.$tunnel['port'];
                }
            }
        }
        
        return $grid_data;
    }

    public function getAction($uuid = null)
    {
        $model = new Autossh();
        if ($uuid != null) {
            $node = $model->getNodeByReference('tunnels.tunnel.'.$uuid);
            if ($node != null) {
                $data = array('tunnel' => $node->getNodes());
                return $data;
            }
        } else {
            $node = $model->tunnels->tunnel->add();
            $data = array('tunnel' => $node->getNodes());
            $data['tunnel']['known_host'] = 'new connection, no known host value';
            return $data;
        }
        return array();
    }
    
    public function infoAction($uuid = null)
    {
        $info = array(
            'title' => 'SSH server known host keys',
            'message' => null,
        );
        if ($uuid != null) {
            $backend = new Backend();
            $configd_run = sprintf('autossh host_keys --connection_uuid=%s', escapeshellarg($uuid));
            $response = json_decode(trim($backend->configdRun($configd_run)), true);
            
            if ($response['status'] === 'success' && isset($response['data']) && count($response['data']) > 0) {
                $info['status'] = 'success'; // required for afterExecuteRoute() trap below
                $info['message'] = '';
                foreach ($response['data'] as $key_value) {
                    $info['message'] = $info['message'].htmlspecialchars($key_value).'<br><br>';
                }
                $info['message'] = preg_replace('/\<br\>\<br\>$/', '', $info['message']);
            } else {
                $info['message'] = $response['message'];
            }
        }
        return $info;
    }
    
    public function setAction($uuid = null)
    {
        $response = array(
            'status'=>'fail',
            'result'=>'failed',
            'message' => 'Invalid request'
        );
        if ($this->request->isPost() && $this->request->hasPost('tunnel')) {
            $model = new Autossh();
            if ($uuid !== null) {
                $node = $model->getNodeByReference('tunnels.tunnel.'.$uuid);
                if ($node !== null) {
                    $post_data = $this->request->getPost('tunnel');
                    $node->setNodes($post_data);
                    $response = $this->save($model, $node, 'tunnel');
                }
            }
        }
        return $response;
    }
    
    public function addAction()
    {
        $response = array(
            'status'=>'fail',
            'result'=>'failed',
            'message' => 'Invalid request'
        );
        if ($this->request->isPost() && $this->request->hasPost('tunnel')) {
            $model = new Autossh();
            $node = $model->tunnels->tunnel->add();
            $post_data = $this->request->getPost('tunnel');
            $node->setNodes($post_data);
            
            $validate = $this->validate($model, $node, 'tunnel');
            if (count($validate['validations']) == 0) {
                $response = $this->save($model, $node, 'tunnel');
            } else {
                $response['validations'] = $validate['validations'];
                $response['message'] = 'Validation errors';
            }
        }
        return $response;
    }
    
    public function delAction($uuid = null)
    {
        $response = array(
            'status'=>'fail',
            'result'=>'failed',
            'message' => 'Invalid request'
        );
        if ($this->request->isPost()) {
            $model = new Autossh();
            if ($uuid != null) {
                $this->stopTunnel($uuid);
                if ($model->tunnels->tunnel->del($uuid)) {
                    $model->serializeToConfig();
                    Config::getInstance()->save();
                    $model->setConfigChangeOn();
                    $response['result'] = 'deleted';
                    $response['message'] = 'Okay, item deleted';
                } else {
                    $response['result'] = 'not found';
                    $response['message'] = 'Failed to delete item';
                }
            }
        }
        return $response;
    }
    
    public function toggleAction($uuid = null)
    {
        $response = array(
            'status'=>'fail',
            'result'=>'failed',
            'message' => 'Invalid request'
        );
        if ($this->request->isPost()) {
            $model = new Autossh();
            if ($uuid != null) {
                $node = $model->getNodeByReference('tunnels.tunnel.'.$uuid);
                if (!empty($node)) {
                    $node_data = $node->getNodes();
                    $toggle_data = array(
                        'enabled' => ((int)$node_data['enabled'] > 0 ? '0' : '1')
                    );
                    $node->setNodes($toggle_data);
                    $response = $this->save($model, $node, 'tunnel');
                    
                    if ($response['status'] == 'success' && $toggle_data['enabled'] == '0') {
                        $this->stopTunnel($uuid);
                    }
                }
            }
        }
        return $response;
    }
    
    private function stopTunnel($uuid)
    {
        $backend = new Backend();
        $configd_run = sprintf('autossh stop_tunnel %s', escapeshellarg($uuid));
        $backend->configdRun($configd_run);
    }
    
    private function save($model, $node = null, $reference = null)
    {
        $result = $this->validate($model, $node, $reference);
        if (count($result['validations']) == 0) {
            $model->serializeToConfig();
            Config::getInstance()->save();
            $model->setConfigChangeOn();
            $result['status'] = 'success';
            $result['result'] = 'saved';
            unset($result['validations']);
        }
        return $result;
    }
    
    private function validate($model, $node = null, $reference = null)
    {
        $result = array('status'=>'fail','validations' => array());
        $validation_messages = $model->performValidation();
        foreach ($validation_messages as $field => $message) {
            if ($node != null) {
                $index = str_replace($node->__reference, $reference, $message->getField());
                $result['validations'][$index] = $message->getMessage();
            } else {
                $result['validations'][$message->getField()] = $message->getMessage();
            }
        }
        return $result;
    }
    
    public function afterExecuteRoute($dispatcher)
    {
        /**
         * In an the limited situation of an "info" action with "success" status we catch
         * the regular afterExecuteRoute() in order to prevent htmlspecialchars() being
         * universally applied because we do require the ability to inject html and the
         * content gets wrapped by htmlspecialchars() in the "info" function.
         */
        if ($dispatcher->getActionName() === "info") {
            $data = $dispatcher->getReturnedValue();
            if (is_array($data) && isset($data['status']) && $data['status'] === 'success') {
                $this->response->setContentType('application/json', 'UTF-8');
                $this->response->setContent(json_encode($data));
                return $this->response->send();
            }
        }
        // all other situations get passed to the parent as usual.
        return parent::afterExecuteRoute($dispatcher);
    }
}
