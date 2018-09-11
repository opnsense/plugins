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

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Base\UIModelGrid;
use \VerbNetworks\Autossh\Autossh;

class ServiceController extends ApiControllerBase
{

    public function isConfigChangeAction()
    {
        $response = array('status'=>'fail', 'message'=>'Invalid request');

        if ($this->request->isGet()) {
            $model = new Autossh();
            return array('status'=>'success', 'data'=>$model->isConfigChange(), 'message'=>null);
        }
        
        return $response;
    }
    
    public function reloadAction()
    {
        $response = array('status'=>'fail', 'message'=>'Invalid request');

        if ($this->request->isPost()) {
            $backend = new Backend();
            
            $backend_template_response = trim($backend->configdRun('template reload VerbNetworks/Autossh'));
            if (strtoupper($backend_template_response) !== 'OK') {
                return array('status'=>'fail',
                    'message'=>'Error while reloading template files, review configd logs for more information');
            }
            
            $backend_autossh_response = trim($backend->configdRun('autossh config_helper'));
            if (empty($backend_autossh_response) || strpos($backend_autossh_response, 'success') === false) {
                if (empty($backend_autossh_response)) {
                    return array('status'=>'fail',
                        'message'=>'Unknown error occured while performing autossh config_helper, '
                        . 'review configd logs for more information');
                }
                $backend_autossh_response = @json_decode($backend_autossh_response, true);
                if (isset($backend_autossh_response['message'])) {
                    return array('status'=>'fail', 'message'=>$backend_autossh_response['message']);
                } else {
                    return array('status'=>'fail', 'message'=>$backend_autossh_response);
                }
                return $response;
            }
            
            $model = new Autossh();
            $model->setConfigChangeOff();
            return array('status'=>'success', 'message'=>'Autossh reload okay');
        }
        
        return $response;
    }

    public function statusAction()
    {
        $response = array('status'=>'fail', 'message'=>'Invalid request');
        if ($this->request->isPost() && $this->request->hasPost('id')) {
            $backend_result = $this->backendStandardAction('status_tunnel', $this->request->getPost('id'));
            if (false === strpos($backend_result, ' not running')) {
                return array('status'=>'running');
            } else {
                return array('status'=>'stopped');
            }
        }

        return $response;
    }

    public function startAction()
    {
        $response = array('status'=>'fail', 'message'=>'Invalid request');
        if ($this->request->isPost() && $this->request->hasPost('id')) {
            $backend_result = $this->backendStandardAction('start_tunnel', $this->request->getPost('id'));
            if (false !== strpos($backend_result, 'Starting autossh tunnel ')) {
                return array('status'=>'success', 'message'=>$backend_result);
            } else {
                return array('status'=>'fail', 'message'=>'Backend request failed');
            }
        }
        return $response;
    }

    public function restartAction()
    {
        $response = array('status'=>'fail', 'message'=>'Invalid request');
        if ($this->request->isPost() && $this->request->hasPost('id')) {
            $backend_result = $this->backendStandardAction('restart_tunnel', $this->request->getPost('id'));
            if (false !== strpos($backend_result, 'Starting autossh tunnel ')) {
                return array('status'=>'success', 'message'=>$backend_result);
            } else {
                return array('status'=>'fail', 'message'=>'Backend request failed');
            }
        }
        return $response;
    }
    
    public function stopAction()
    {
        $response = array('status'=>'fail', 'message'=>'Invalid request');
        if ($this->request->isPost() && $this->request->hasPost('id')) {
            $backend_result = $this->backendStandardAction('stop_tunnel', $this->request->getPost('id'));
            if (false !== strpos($backend_result, 'Stopping autossh tunnel ')) {
                return array('status'=>'success', 'message'=>$backend_result);
            } else {
                return array('status'=>'fail', 'message'=>'Backend request failed');
            }
        }
        return $response;
    }
    
    public function connectionStatusAction()
    {
        $response = array(
            'current'=>1,
            'rowCount'=>null,
            'rows'=>array(),
            'total'=>null
        );
        
        if ($this->request->isGet()) {
            $model = new Autossh();
            $grid = new UIModelGrid($model->tunnels->tunnel);
        
            $grid_data = $grid->fetchBindRequest($this->request, array(
                'enabled', 'user', 'hostname', 'port', 'bind_interface', 'ssh_key',
                'local_forward', 'remote_forward', 'dynamic_forward', 'tunnel_device'
            ), 'hostname');
            
            foreach ($grid_data['rows'] as $tunnel) {
                $connection = $tunnel['user'].'@'.$tunnel['hostname'];
                if (!empty($tunnel['port'])) {
                    $connection .= ':'.$tunnel['port'];
                }
                
                $forward_data = array(
                    'local'=>$tunnel['local_forward'],
                    'dynamic'=>$tunnel['dynamic_forward'],
                    'remote'=>$tunnel['remote_forward'],
                    'tunnel'=>$tunnel['tunnel_device']
                );
                
                $backend = new Backend();
                $configd_run = sprintf(
                    'autossh connection_status --connection_uuid=%s',
                    escapeshellarg($tunnel['uuid'])
                );
                $backend_result = @json_decode(trim($backend->configdRun($configd_run)), true);
                
                $status_data = array('enabled'=>null);
                if (isset($backend_result['status']) && $backend_result['status'] === 'success') {
                    $last_healthy = (int)strtotime($backend_result['data']['last_healthy']);
                    if (empty($last_healthy)) {
                        if (!empty($backend_result['data']['uptime'])) {
                            $last_healthy = -1;
                        } else {
                            $last_healthy = null;
                        }
                    } else {
                        $last_healthy = (int)(time() - $last_healthy);
                    }
                    
                    $status_data = array(
                        'enabled'=>$backend_result['data']['enabled'],
                        'uptime'=>$backend_result['data']['uptime'],
                        'last_healthy'=>$last_healthy,
                        'starts'=> $backend_result['data']['starts'],
                    );
                }
                
                $response['rows'][] = array(
                    'uuid'=>$tunnel['uuid'],
                    'connection'=>$connection,
                    'bind_interface'=>$tunnel['bind_interface'],
                    'forwards'=>$forward_data,
                    'ssh_key'=>$tunnel['ssh_key'],
                    'status'=>$status_data
                );
            }
        }
        
        $response['rowCount'] = count($response['rows']);
        $response['total'] = count($response['rows']);
        
        return $response;
    }
    
    private function backendStandardAction($action, $uuid)
    {
        $backend = new Backend();
        $configd_run = sprintf('autossh %s %s', escapeshellarg($action), escapeshellarg($uuid));
        return trim($backend->configdRun($configd_run));
    }
}
