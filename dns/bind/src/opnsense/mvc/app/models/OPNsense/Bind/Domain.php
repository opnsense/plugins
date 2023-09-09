<?php

/*
    Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
    All rights reserved.
    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:
    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.
    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

namespace OPNsense\Bind;

use OPNsense\Base\BaseModel;

class Domain extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function serializeToConfig($validateFullModel = false, $disable_validation = false)
    {
        $serialsToSet = array();
        // collected changed records
        foreach ($this->getFlatNodes() as $key => $node) {
            if ($node->isFieldChanged() && (string)$node !== "") {
                $domain = $node->getParentNode();
                if (empty($serialsToSet[$domain->getAttribute('uuid')])) {
                    $serialsToSet[$domain->getAttribute('uuid')] = $domain;
                }
            }
        }
        // new serials on changed records
        $lastupdate = (string)time();
        foreach ($serialsToSet as $domain) {
            $domain->serial = $lastupdate;
        }
        return parent::serializeToConfig($validateFullModel, $disable_validation);
    }

    /**
     * @param $uuid string domain uuid to update
     * @return Domain
     */
    public function updateSerial($uuid)
    {
        foreach ($this->domains->domain->iterateItems() as $domain) {
            if ($domain->getAttribute('uuid') == $uuid) {
                $domain->serial = (string)time();
                return $this;
            }
        }
        return $this;
    }
}
