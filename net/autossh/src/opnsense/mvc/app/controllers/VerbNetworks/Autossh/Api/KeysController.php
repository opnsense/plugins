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

class KeysController extends ApiControllerBase
{
    
    public function searchAction()
    {
        $this->sessionClose(); // close out long running actions
        $model = new Autossh();
        $grid = new UIModelGrid($model->keys->key);
        
        $grid_data = $grid->fetchBindRequest(
            $this->request,
            array('name', 'type', 'key_fingerprint', 'timestamp'),
            'name'
        );
        
        foreach ($grid_data['rows'] as $row_index => $row_data) {
            foreach ($row_data as $key => $value) {
                if ($key === 'timestamp') {
                    $grid_data['rows'][$row_index][$key] = date('Y-m-d H:i:s', $value);
                } elseif ($key === 'key_fingerprint') {
                    $fingerprint_elements = explode(' ', $value);
                    $grid_data['rows'][$row_index][$key] = str_replace(
                        'md5:',
                        '',
                        strtolower($fingerprint_elements[1])
                    );
                }
            }
        }
        
        return $grid_data;
    }
    
    public function getAction($uuid = null)
    {
        $model = new Autossh();
        if ($uuid != null) {
            $node = $model->getNodeByReference('keys.key.'.$uuid);
            if ($node != null) {
                $data = $node->getNodes();
                
                // munge the data a little bit making it easier to use
                $data['timestamp'] = date('Y-m-d H:i:s', $data['timestamp']);
                $fingerprint_elements = explode(' ', $data['key_fingerprint']);
                $data['key_fingerprint'] = str_replace('md5:', '', strtolower($fingerprint_elements[1]));
                unset($data['key_private']);
                
                return array('key'=>$data);
            }
        } else {
            $node = $model->keys->key->add();
            return array('key'=>$node->getNodes());
        }
        return array();
    }
    
    public function infoAction($uuid = null)
    {
        $ssh_key_restrictions = 'command="",no-agent-forwarding,no-pty,no-user-rc,no-X11-forwarding';
        $info = array(
            'title'=>'SSH public key',
            'message'=>'Unknown ssh-key',
        );
        if ($uuid != null) {
            $model = new Autossh();
            $node = $model->getNodeByReference('keys.key.'.$uuid);
            if ($node != null) {
                $node_data = $node->getNodes();
                $key_public = base64_decode($node_data['key_public']);

                // replace host_id comment with this key uuid
                $key_public = preg_replace('/host_id:.*?$/', $uuid, $key_public);
                
                // prepend ssh-key restrictions
                $key_public = $ssh_key_restrictions.' '.$key_public;

                $info['message'] = $key_public;
            }
        }
        return $info;
    }

    public function setAction($uuid)
    {
        $response = array(
            'status'=>'fail',
            'result'=>'failed',
            'message'=>'Invalid request'
        );
        if ($this->request->isPost() && $this->request->hasPost('key')) {
            $model = new Autossh();
            if ($uuid != null) {
                $node = $model->getNodeByReference('keys.key.'.$uuid);
                if ($node != null) {
                    $post_data = $this->request->getPost('key');
                    unset($post_data['type']);
                    $node->setNodes($post_data);
                    return $this->save($model, $node, 'key');
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
            'message'=>'Invalid request'
        );
        if ($this->request->isPost() && $this->request->hasPost('key')) {
            $model = new Autossh();
            $node = $model->keys->key->add();
            $post_data = $this->request->getPost('key');
            $node->setNodes($post_data);
            
            $validate = $this->validate($model, $node, 'key');
            if (count($validate['validations']) == 0) {
                $backend = new Backend();
                $configd_run = sprintf('autossh key_gen --key_type=%s', escapeshellarg($post_data['type']));
                $backend_response = json_decode(trim($backend->configdRun($configd_run)), true);
                if (empty($backend_response)) {
                    return array('status'=>'fail', 'message'=>'Error calling autossh key_gen via configd');
                } elseif ($backend_response['status'] === 'success') {
                    $node->setNodes(array_merge($post_data, $backend_response['data']));
                    return $this->save($model, $node, 'key');
                }
            } else {
                return array(
                    'status'=>'fail',
                    'result'=>'failed',
                    'validations'=>$validate['validations'],
                    'message'=>'Validation errors'
                );
            }
        }
        return $response;
    }

    public function delAction($uuid = null)
    {
        $response = array('status'=>'fail', 'message'=>'Invalid request');
        if ($this->request->isPost()) {
            $model = new Autossh();
            if ($uuid != null) {
                if ($model->keys->key->del($uuid)) {
                    $model->serializeToConfig();
                    Config::getInstance()->save();
                    return array('status'=>'success', 'result'=>'deleted', 'message'=>'Okay, item deleted');
                } else {
                    return array('status'=>'fail', 'result'=>'not found', 'message'=>'Item not found, nothing deleted');
                }
            }
        }
        return $response;
    }
    
    private function save($model, $node = null, $reference = null)
    {
        $result = $this->validate($model, $node, $reference);
        if (count($result['validations']) == 0) {
            $model->serializeToConfig();
            Config::getInstance()->save();
            $result['status'] = 'success';
            $result['result'] = 'saved';
            unset($result['validations']);
        }
        return $result;
    }
    
    private function validate($model, $node = null, $reference = null)
    {
        $result = array('status'=>'fail', 'validations'=>array());
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
}
