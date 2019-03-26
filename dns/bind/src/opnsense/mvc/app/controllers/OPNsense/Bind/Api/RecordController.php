<?php
/**
 *    Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Bind\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;

class RecordController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'record';
    protected static $internalModelClass = '\OPNsense\Bind\Record';

    public function searchRecordAction()
    {
        $domain = $this->request->get('domain');
        $filter_funct = null;
        if (!empty($domain)) {
            $filter_funct = function($record) use ($domain) {
                return $record->domain == $domain;
            };
        }

        return $this->searchBase('records.record', array("enabled", "domain", "name", "type", "value"), null, $filter_funct);
    }
    public function getRecordAction($uuid = null)
    {
        $this->sessionClose();
        $domain = $this->request->get('domain');
        $result = $this->getBase('record', 'records.record', $uuid);
        if ($uuid == null && !empty($result['record']['domain'])) {
            // set domain selection
            foreach ($result['record']['domain'] as $key => &$value) {
                if ($key == $domain) {
                    $value['selected'] = 1;
                } else {
                    $value['selected'] = 0;
                }
            }
        }
        return $result;
    }
    public function addRecordAction($uuid = null)
    {
        if ($this->request->isPost() && $this->request->hasPost("record")) {
            if ($uuid != null) {
                $node = $this->getModel()->getNodeByReference('records.record.'.$uuid);
            } else {
                $node = $this->getModel()->records->record->Add();
            }
            $node->setNodes($this->request->getPost("record"));
            if (empty((string)$node->serial)) {
                // set timestamp
                $backend = new Backend();
                $serial = $backend->configdpRun("bind genserial");
                $node->serial = $serial;
            }
            return $this->validateAndSave($node, 'record');
        }
        return array("result"=>"failed");
    }
    public function delRecordAction($uuid)
    {
        return $this->delBase('records.record', $uuid);
    }
    public function setRecordAction($uuid = null)
    {
        if ($this->request->isPost() && $this->request->hasPost("record")) {
            if ($uuid != null) {
                $node = $this->getModel()->getNodeByReference('records.record.'.$uuid);
            } else {
                $node = $this->getModel()->records->record->Add();
            }
            $node->setNodes($this->request->getPost("record"));
            if (empty((string)$node->serial)) {
                // set timestamp
                $backend = new Backend();
                $serial = $backend->configdpRun("bind genserial");
                $node->serial = $serial;
            }
            return $this->validateAndSave($node, 'record');
        }
        return array("result"=>"failed");
    }
    public function toggleRecordAction($uuid)
    {
        return $this->toggleBase('records.record', $uuid);
    }
}
