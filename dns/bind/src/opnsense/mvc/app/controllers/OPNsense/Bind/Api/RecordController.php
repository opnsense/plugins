<?php

/*
 * Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
 * Copyright (C) 2019 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Bind\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Bind\Domain;
use OPNsense\Bind\Record;
use OPNsense\Core\Config;

class RecordController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'record';
    protected static $internalModelClass = '\OPNsense\Bind\Record';

    /**
     * resolve the domains of the given records
     * @param $uuids string comma separated list of record uuids
     * @return array domain uuids
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    private function getRecordDomains($uuids)
    {
        $domains = [];
        $mdl = new Record();
        foreach (explode(',', (string)$uuids) as $uuid) {
            $record = $mdl->getRecord($uuid);
            if ($record !== null) {
                $domains[(string)$record->domain] = true;
            }
        }
        return array_keys($domains);
    }

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
                $this->updateDomainSerial((string)$record->domain);
            }
        }
    }

    /**
     * update domain serial(s)
     * @param $domains string|array domain uuid or list of domain uuids
     * @throws \OPNsense\Base\ModelException
     * @throws \ReflectionException
     */
    private function updateDomainSerial($domains)
    {
        if (!empty($domains) && $this->request->isPost()) {
            $mdl = new Domain();
            foreach ((array)$domains as $domain) {
                $mdl->updateSerial($domain);
            }
            $mdl->serializeToConfig();
            Config::getInstance()->save();
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
        // records are gone after delBase(), resolve their domains first
        $domains = $this->getRecordDomains($uuid);
        $result =  $this->delBase('records.record', $uuid);
        if ($result['result'] == 'deleted') {
            $this->updateDomainSerial($domains);
        }
        return $result;
    }

    public function setRecordAction($uuid = null)
    {
        // the record may be moved to another zone, which needs a new serial as well
        $old_domains = $this->getRecordDomains($uuid);
        $result =  $this->setBase('record', 'records.record', $uuid);
        if ($result['result'] == 'saved') {
            $this->updateDomainSerial(
                array_unique(array_merge($old_domains, $this->getRecordDomains($uuid)))
            );
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
