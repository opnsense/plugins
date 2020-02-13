<?php

/**
 *    Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
 *    Copyright (C) 2019 Deciso B.V.
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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Bind\Domain;
use OPNsense\Core\Config;

class RecordController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'record';
    protected static $internalModelClass = '\OPNsense\Bind\Record';

    /**
     * update parent domain serial
     * @param $uuid string
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    private function setDomainSerial($uuid)
    {
        if ($this->request->isPost()) {
            $record  = $this->getModel()->getRecord($uuid);
            if ($record !== null) {
                (new Domain())->updateSerial((string)$record->domain)->serializeToConfig();
                Config::getInstance()->save();
            }
        }
    }

    public function searchRecordAction()
    {
        $domain = $this->request->get('domain');
        $filter_funct = null;
        if (!empty($domain)) {
            $filter_funct = function ($record) use ($domain) {
                return $record->domain == $domain;
            };
        }

        return $this->searchBase(
            'records.record',
            array("enabled", "domain", "name", "type", "value"),
            null,
            $filter_funct
        );
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

    public function addRecordAction()
    {
        $result = $this->addBase('record', 'records.record');
        if (!empty($result['uuid'])) {
            $this->setDomainSerial($result['uuid']);
        }
        return $result;
    }

    public function delRecordAction($uuid)
    {
        $result =  $this->delBase('records.record', $uuid);
        if ($result['result'] == 'deleted') {
            $this->setDomainSerial($uuid);
        }
        return $result;
    }

    public function setRecordAction($uuid = null)
    {
        $result =  $this->setBase('record', 'records.record', $uuid);
        if ($result['result'] == 'saved') {
            $this->setDomainSerial($uuid);
        }
        return $result;
    }

    public function toggleRecordAction($uuid)
    {
        $result =  $this->toggleBase('records.record', $uuid);
        if (!empty($result['changed'])) {
            $this->setDomainSerial($uuid);
        }
        return $result;
    }
}
