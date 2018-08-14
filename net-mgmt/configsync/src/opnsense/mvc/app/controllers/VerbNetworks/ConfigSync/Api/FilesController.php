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

namespace VerbNetworks\ConfigSync\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

class FilesController extends ApiControllerBase
{
    public function listAction()
    {
        $response = array('status'=>'fail', 'message' => 'Invalid request');
        
        if ($this->request->isPost()) {
            $current_page = 1;
            if ($this->request->hasPost('current')) {
                $current_page = (int)$this->request->getPost('current');
            }
            
            $row_count = -1;
            if ($this->request->hasPost('rowCount')) {
                $row_count = (int)$this->request->getPost('rowCount');
            }
            
            $filter = '';
            if ($this->request->hasPost('searchPhrase')) {
                $filter = (string)$this->request->getPost('searchPhrase');
            }
            
            $backend = new Backend();
            $configd_run = sprintf(
                'configsync awss3_get_file_list --filter=%s',
                escapeshellarg($filter)
            );
            $backend_response = json_decode(trim($backend->configdRun($configd_run)), true);
            
            if ($backend_response['status'] !== 'success') {
                return $response;
            }
            
            $response_dataset = array(
                'current'=> $current_page,
                'rowCount'=> 0,
                'rows'=> array(),
                'total'=> count($backend_response['data']),
            );
            
            $index_first = ($current_page - 1) * $row_count;
            if ($row_count >= 0) {
                $index_last = ($current_page * $row_count) - 1;
            } else {
                $index_last = count($backend_response['data']) - 1;
            }
            
            foreach ($backend_response['data'] as $row_index => $properties) {
                if ($row_index >= $index_first && $row_index <= $index_last) {
                    array_push($response_dataset['rows'], array(
                        'timestamp_created'=> $properties['Created'],
                        'timestamp_synced'=> $properties['LastModified'],
                        'path'=> $properties['Key'],
                        'storage_class'=> $properties['StorageClass'],
                        'storage_size'=> $properties['Size'],
                        'storage_etag'=> $properties['ETag'],
                    ));
                }
            };
            $response_dataset['rowCount'] = count($response_dataset['rows']);
            
            return $response_dataset;
        }
        
        return $response;
    }
}
